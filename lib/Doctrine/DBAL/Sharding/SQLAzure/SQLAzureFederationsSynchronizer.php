<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;

use Doctrine\DBAL\Schema\Synchronizer\AbstractSchemaSynchronizer;
use Doctrine\DBAL\Sharding\SingleDatabaseSynchronizer;

/**
 * SQL Azure Schema Synchronizer
 *
 * Will iterate over all shards when performing schema operations. This is done
 * by partitioning the passed schema into subschemas for the federation and the
 * global database and then applying the operations step by step using the
 * {@see \Doctrine\DBAL\Sharding\SingleDatabaseSynchronizer}.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class SQLAzureFederationsSynchronizer extends AbstractSchemaSynchronizer
{
    const FEDERATION_TABLE_FEDERATED   = 'azure.federated';
    const FEDERATION_DISTRIBUTION_NAME = 'azure.federatedOnDistributionName';


    /**
     * @var SQLAzureShardManager
     */
    private $shardManager;

    /**
     * @var SchemaSynchronizer
     */
    private $synchronizer;

    public function __construct(Connection $conn, SQLAzureShardManager $shardManager, SchemaSynchronizer $sync = null)
    {
        parent::__construct($conn);
        $this->shardManager = $shardManager;
        $this->synchronizer = $sync ?: new SingleDatabaseSynchronizer($conn);
    }

    /**
     * Get the SQL statements that can be executed to create the schema.
     *
     * @param Schema $createSchema
     * @return array
     */
    public function getCreateSchema(Schema $createSchema)
    {
        $sql = array();

        list($global, $federation) = $this->partitionSchema($createSchema);

        $globalSql = $this->synchronizer->getCreateSchema($global);
        if ($globalSql) {
            $sql[] = "-- Create Root Federation\n" .
                     "USE FEDERATION ROOT WITH RESET;";
            $sql = array_merge($sql, $globalSql);
        }

        $federationSql = $this->synchronizer->getCreateSchema($federation);

        if ($federationSql) {
            $defaultValue = $this->getFederationTypeDefaultValue();

            $sql[] = $this->getCreateFederationStatement();
            $sql[] = "USE FEDERATION " . $this->shardManager->getFederationName() . " (" . $this->shardManager->getDistributionKey() . " = " . $defaultValue . ") WITH RESET, FILTERING = OFF;";
            $sql = array_merge($sql, $federationSql);
        }

        return $sql;
    }

    /**
     * Get the SQL Statements to update given schema with the underlying db.
     *
     * @param Schema $toSchema
     * @param bool $noDrops
     * @return array
     */
    public function getUpdateSchema(Schema $toSchema, $noDrops = false)
    {
        return $this->work($toSchema, function($synchronizer, $schema) use ($noDrops) {
            return $synchronizer->getUpdateSchema($schema, $noDrops);
        });
    }

    /**
     * Get the SQL Statements to drop the given schema from underlying db.
     *
     * @param Schema $dropSchema
     * @return array
     */
    public function getDropSchema(Schema $dropSchema)
    {
        return $this->work($dropSchema, function($synchronizer, $schema) {
            return $synchronizer->getDropSchema($schema);
        });
    }

    /**
     * Create the Schema
     *
     * @param Schema $createSchema
     * @return void
     */
    public function createSchema(Schema $createSchema)
    {
        $this->processSql($this->getCreateSchema($createSchema));
    }

    /**
     * Update the Schema to new schema version.
     *
     * @param Schema $toSchema
     * @return void
     */
    public function updateSchema(Schema $toSchema, $noDrops = false)
    {
        $this->processSql($this->getUpdateSchema($toSchema, $noDrops));
    }

    /**
     * Drop the given database schema from the underlying db.
     *
     * @param Schema $dropSchema
     * @return void
     */
    public function dropSchema(Schema $dropSchema)
    {
        $this->processSqlSafely($this->getDropSchema($dropSchema));
    }

    /**
     * Get the SQL statements to drop all schema assets from underlying db.
     *
     * @return array
     */
    public function getDropAllSchema()
    {
        $this->shardManager->selectGlobal();
        $globalSql = $this->synchronizer->getDropAllSchema();

        if ($globalSql) {
            $sql[] = "-- Work on Root Federation\nUSE FEDERATION ROOT WITH RESET;";
            $sql = array_merge($sql, $globalSql);
        }

        $shards = $this->shardManager->getShards();
        foreach ($shards as $shard) {
            $this->shardManager->selectShard($shard['rangeLow']);

            $federationSql = $this->synchronizer->getDropAllSchema();
            if ($federationSql) {
                $sql[] = "-- Work on Federation ID " . $shard['id'] . "\n" .
                         "USE FEDERATION " . $this->shardManager->getFederationName() . " (" . $this->shardManager->getDistributionKey() . " = " . $shard['rangeLow'].") WITH RESET, FILTERING = OFF;";
                $sql = array_merge($sql, $federationSql);
            }
        }

        $sql[] = "USE FEDERATION ROOT WITH RESET;";
        $sql[] = "DROP FEDERATION " . $this->shardManager->getFederationName();

        return $sql;
    }

    /**
     * Drop all assets from the underyling db.
     *
     * @return void
     */
    public function dropAllSchema()
    {
        $this->processSqlSafely($this->getDropAllSchema());
    }

    private function partitionSchema(Schema $schema)
    {
        return array(
            $this->extractSchemaFederation($schema, false),
            $this->extractSchemaFederation($schema, true),
        );
    }

    private function extractSchemaFederation(Schema $schema, $isFederation)
    {
        $partionedSchema = clone $schema;

        foreach ($partionedSchema->getTables() as $table) {
            if ($isFederation) {
                $table->addOption(self::FEDERATION_DISTRIBUTION_NAME, $this->shardManager->getDistributionKey());
            }

            if ( $table->hasOption(self::FEDERATION_TABLE_FEDERATED) !== $isFederation) {
                $partionedSchema->dropTable($table->getName());
            } else {
                foreach ($table->getForeignKeys() as $fk) {
                    $foreignTable = $schema->getTable($fk->getForeignTableName());
                    if ($foreignTable->hasOption(self::FEDERATION_TABLE_FEDERATED) !== $isFederation) {
                        throw new \RuntimeException("Cannot have foreign key between global/federation.");
                    }
                }
            }
        }

        return $partionedSchema;
    }

    /**
     * Work on the Global/Federation based on currently existing shards and
     * perform the given operation on the underyling schema synchronizer given
     * the different partioned schema instances.
     *
     * @param Schema $schema
     * @param Closure $operation
     * @return array
     */
    private function work(Schema $schema, \Closure $operation)
    {
        list($global, $federation) = $this->partitionSchema($schema);
        $sql = array();

        $this->shardManager->selectGlobal();
        $globalSql = $operation($this->synchronizer, $global);

        if ($globalSql) {
            $sql[] = "-- Work on Root Federation\nUSE FEDERATION ROOT WITH RESET;";
            $sql   = array_merge($sql, $globalSql);
        }

        $shards = $this->shardManager->getShards();

        foreach ($shards as $shard) {
            $this->shardManager->selectShard($shard['rangeLow']);

            $federationSql = $operation($this->synchronizer, $federation);
            if ($federationSql) {
                $sql[] = "-- Work on Federation ID " . $shard['id'] . "\n" .
                         "USE FEDERATION " . $this->shardManager->getFederationName() . " (" . $this->shardManager->getDistributionKey() . " = " . $shard['rangeLow'].") WITH RESET, FILTERING = OFF;";
                $sql   = array_merge($sql, $federationSql);
            }
        }

        return $sql;
    }

    private function getFederationTypeDefaultValue()
    {
        $federationType = Type::getType($this->shardManager->getDistributionType());

        switch ($federationType->getName()) {
            case Type::GUID:
                $defaultValue = '00000000-0000-0000-0000-000000000000';
                break;
            case Type::INTEGER:
            case Type::SMALLINT:
            case Type::BIGINT:
                $defaultValue = '0';
                break;
            default:
                $defaultValue = '';
                break;
        }
        return $defaultValue;
    }

    private function getCreateFederationStatement()
    {
        $federationType = Type::getType($this->shardManager->getDistributionType());
        $federationTypeSql = $federationType->getSqlDeclaration(array(), $this->conn->getDatabasePlatform());

        return "--Create Federation\n" .
               "CREATE FEDERATION " . $this->shardManager->getFederationName() . " (" . $this->shardManager->getDistributionKey() . " " . $federationTypeSql ."  RANGE)";
    }
}


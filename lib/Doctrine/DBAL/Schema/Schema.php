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
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector;
use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;
use Doctrine\DBAL\Schema\Visitor\Visitor;

/**
 * Object representation of a database schema
 *
 * Different vendors have very inconsistent naming with regard to the concept
 * of a "schema". Doctrine understands a schema as the entity that conceptually
 * wraps a set of database objects such as tables, sequences, indexes and
 * foreign keys that belong to each other into a namespace. A Doctrine Schema
 * has nothing to do with the "SCHEMA" defined as in PostgreSQL, it is more
 * related to the concept of "DATABASE" that exists in MySQL and PostgreSQL.
 *
 * Every asset in the doctrine schema has a name. A name consists of either a
 * namespace.local name pair or just a local unqualified name.
 *
 * The abstraction layer that covers a PostgreSQL schema is the namespace of an
 * database object (asset). A schema can have a name, which will be used as
 * default namespace for the unqualified database objects that are created in
 * the schema.
 *
 * In the case of MySQL where cross-database queries are allowed this leads to
 * databases being "misinterpreted" as namespaces. This is intentional, however
 * the CREATE/DROP SQL visitors will just filter this queries and do not
 * execute them. Only the queries for the currently connected database are
 * executed.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
class Schema extends AbstractAsset
{
    /**
     * @var array
     */
    protected $_tables = array();

    /**
     * @var array
     */
    protected $_sequences = array();

    /**
     * @var SchemaConfig
     */
    protected $_schemaConfig = false;

    /**
     * @param array $tables
     * @param array $sequences
     * @param array $views
     * @param array $triggers
     * @param SchemaConfig $schemaConfig
     */
    public function __construct(array $tables=array(), array $sequences=array(), SchemaConfig $schemaConfig=null)
    {
        if ($schemaConfig == null) {
            $schemaConfig = new SchemaConfig();
        }
        $this->_schemaConfig = $schemaConfig;
        $this->_setName($schemaConfig->getName() ?: 'public');

        foreach ($tables AS $table) {
            $this->_addTable($table);
        }
        foreach ($sequences AS $sequence) {
            $this->_addSequence($sequence);
        }
    }

    /**
     * @return bool
     */
    public function hasExplicitForeignKeyIndexes()
    {
        return $this->_schemaConfig->hasExplicitForeignKeyIndexes();
    }

    /**
     * @param Table $table
     */
    protected function _addTable(Table $table)
    {
        $tableName = $table->getFullQualifiedName($this->getName());
        if(isset($this->_tables[$tableName])) {
            throw SchemaException::tableAlreadyExists($tableName);
        }

        $this->_tables[$tableName] = $table;
        $table->setSchemaConfig($this->_schemaConfig);
    }

    /**
     * @param Sequence $sequence
     */
    protected function _addSequence(Sequence $sequence)
    {
        $seqName = $sequence->getFullQualifiedName($this->getName());
        if (isset($this->_sequences[$seqName])) {
            throw SchemaException::sequenceAlreadyExists($seqName);
        }
        $this->_sequences[$seqName] = $sequence;
    }

    /**
     * Get all tables of this schema.
     *
     * @return array
     */
    public function getTables()
    {
        return $this->_tables;
    }

    /**
     * @param string $tableName
     * @return Table
     */
    public function getTable($tableName)
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);
        if (!isset($this->_tables[$tableName])) {
            throw SchemaException::tableDoesNotExist($tableName);
        }

        return $this->_tables[$tableName];
    }

    /**
     * @return string
     */
    private function getFullQualifiedAssetName($name)
    {
        if (strpos($name, ".") === false) {
            $name = $this->getName() . "." . $name;
        }
        return strtolower($name);
    }

    /**
     * Does this schema have a table with the given name?
     *
     * @param  string $tableName
     * @return Schema
     */
    public function hasTable($tableName)
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);
        return isset($this->_tables[$tableName]);
    }

    /**
     * Get all table names, prefixed with a schema name, even the default one
     * if present.
     *
     * @return array
     */
    public function getTableNames()
    {
        return array_keys($this->_tables);
    }

    public function hasSequence($sequenceName)
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);
        return isset($this->_sequences[$sequenceName]);
    }

    /**
     * @throws SchemaException
     * @param  string $sequenceName
     * @return Doctrine\DBAL\Schema\Sequence
     */
    public function getSequence($sequenceName)
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);
        if(!$this->hasSequence($sequenceName)) {
            throw SchemaException::sequenceDoesNotExist($sequenceName);
        }
        return $this->_sequences[$sequenceName];
    }

    /**
     * @return Doctrine\DBAL\Schema\Sequence[]
     */
    public function getSequences()
    {
        return $this->_sequences;
    }

    /**
     * Create a new table
     *
     * @param  string $tableName
     * @return Table
     */
    public function createTable($tableName)
    {
        $table = new Table($tableName);
        $this->_addTable($table);
        return $table;
    }

    /**
     * Rename a table
     *
     * @param string $oldTableName
     * @param string $newTableName
     * @return Schema
     */
    public function renameTable($oldTableName, $newTableName)
    {
        $table = $this->getTable($oldTableName);
        $table->_setName($newTableName);

        $this->dropTable($oldTableName);
        $this->_addTable($table);
        return $this;
    }

    /**
     * Drop a table from the schema.
     *
     * @param string $tableName
     * @return Schema
     */
    public function dropTable($tableName)
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);
        $table = $this->getTable($tableName);
        unset($this->_tables[$tableName]);
        return $this;
    }

    /**
     * Create a new sequence
     *
     * @param  string $sequenceName
     * @param  int $allocationSize
     * @param  int $initialValue
     * @return Sequence
     */
    public function createSequence($sequenceName, $allocationSize=1, $initialValue=1)
    {
        $seq = new Sequence($sequenceName, $allocationSize, $initialValue);
        $this->_addSequence($seq);
        return $seq;
    }

    /**
     * @param string $sequenceName
     * @return Schema
     */
    public function dropSequence($sequenceName)
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);
        unset($this->_sequences[$sequenceName]);
        return $this;
    }

    /**
     * Return an array of necessary sql queries to create the schema on the given platform.
     *
     * @param AbstractPlatform $platform
     * @return array
     */
    public function toSql(\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        $sqlCollector = new CreateSchemaSqlCollector($platform);
        $this->visit($sqlCollector);

        return $sqlCollector->getQueries();
    }

    /**
     * Return an array of necessary sql queries to drop the schema on the given platform.
     *
     * @param AbstractPlatform $platform
     * @return array
     */
    public function toDropSql(\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        $dropSqlCollector = new DropSchemaSqlCollector($platform);
        $this->visit($dropSqlCollector);

        return $dropSqlCollector->getQueries();
    }

    /**
     * @param Schema $toSchema
     * @param AbstractPlatform $platform
     */
    public function getMigrateToSql(Schema $toSchema, \Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($this, $toSchema);
        return $schemaDiff->toSql($platform);
    }

    /**
     * @param Schema $fromSchema
     * @param AbstractPlatform $platform
     */
    public function getMigrateFromSql(Schema $fromSchema, \Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($fromSchema, $this);
        return $schemaDiff->toSql($platform);
    }

    /**
     * @param Visitor $visitor
     */
    public function visit(Visitor $visitor)
    {
        $visitor->acceptSchema($this);

        foreach ($this->_tables AS $table) {
            $table->visit($visitor);
        }
        foreach ($this->_sequences AS $sequence) {
            $sequence->visit($visitor);
        }
    }

    /**
     * Cloning a Schema triggers a deep clone of all related assets.
     *
     * @return void
     */
    public function __clone()
    {
        foreach ($this->_tables AS $k => $table) {
            $this->_tables[$k] = clone $table;
        }
        foreach ($this->_sequences AS $k => $sequence) {
            $this->_sequences[$k] = clone $sequence;
        }
    }
}

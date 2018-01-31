<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector;
use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;
use Doctrine\DBAL\Schema\Visitor\NamespaceVisitor;
use Doctrine\DBAL\Schema\Visitor\Visitor;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Object representation of a database schema.
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
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Schema extends AbstractAsset
{
    /**
     * The namespaces in this schema.
     *
     * @var array
     */
    private $namespaces = [];

    /**
     * @var \Doctrine\DBAL\Schema\Table[]
     */
    protected $tables = [];

    /**
     * @var \Doctrine\DBAL\Schema\Sequence[]
     */
    protected $sequences = [];

    /**
     * @var \Doctrine\DBAL\Schema\SchemaConfig
     */
    protected $schemaConfig = false;

    /**
     * @param \Doctrine\DBAL\Schema\Table[]      $tables
     * @param \Doctrine\DBAL\Schema\Sequence[]   $sequences
     * @param \Doctrine\DBAL\Schema\SchemaConfig $schemaConfig
     * @param array                              $namespaces
     */
    public function __construct(
        array $tables = [],
        array $sequences = [],
        SchemaConfig $schemaConfig = null,
        array $namespaces = []
    ) {
        if ($schemaConfig == null) {
            $schemaConfig = new SchemaConfig();
        }
        $this->schemaConfig = $schemaConfig;
        $this->setName($schemaConfig->getName() ?: 'public');

        foreach ($namespaces as $namespace) {
            $this->createNamespace($namespace);
        }

        foreach ($tables as $table) {
            $this->addTable($table);
        }

        foreach ($sequences as $sequence) {
            $this->addSequence($sequence);
        }
    }

    /**
     * @return boolean
     */
    public function hasExplicitForeignKeyIndexes()
    {
        return $this->schemaConfig->hasExplicitForeignKeyIndexes();
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table $table
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function addTable(Table $table)
    {
        $namespaceName = $table->getNamespaceName();
        $tableName = $table->getFullQualifiedName($this->getName());

        if (isset($this->tables[$tableName])) {
            throw SchemaException::tableAlreadyExists($tableName);
        }

        if ( ! $table->isInDefaultNamespace($this->getName()) && ! $this->hasNamespace($namespaceName)) {
            $this->createNamespace($namespaceName);
        }

        $this->tables[$tableName] = $table;
        $table->setSchemaConfig($this->schemaConfig);
    }

    /**
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function addSequence(Sequence $sequence)
    {
        $namespaceName = $sequence->getNamespaceName();
        $seqName = $sequence->getFullQualifiedName($this->getName());

        if (isset($this->sequences[$seqName])) {
            throw SchemaException::sequenceAlreadyExists($seqName);
        }

        if ( ! $sequence->isInDefaultNamespace($this->getName()) && ! $this->hasNamespace($namespaceName)) {
            $this->createNamespace($namespaceName);
        }

        $this->sequences[$seqName] = $sequence;
    }

    /**
     * Returns the namespaces of this schema.
     *
     * @return array A list of namespace names.
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * Gets all tables of this schema.
     *
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    public function getTables()
    {
        return $this->tables;
    }

    /**
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\Schema\Table
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function getTable($tableName)
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);
        if (!isset($this->tables[$tableName])) {
            throw SchemaException::tableDoesNotExist($tableName);
        }

        return $this->tables[$tableName];
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function getFullQualifiedAssetName($name)
    {
        $name = $this->getUnquotedAssetName($name);

        if (strpos($name, ".") === false) {
            $name = $this->getName() . "." . $name;
        }

        return strtolower($name);
    }

    /**
     * Returns the unquoted representation of a given asset name.
     *
     * @param string $assetName Quoted or unquoted representation of an asset name.
     *
     * @return string
     */
    private function getUnquotedAssetName($assetName)
    {
        if ($this->isIdentifierQuoted($assetName)) {
            return $this->trimQuotes($assetName);
        }

        return $assetName;
    }

    /**
     * Does this schema have a namespace with the given name?
     *
     * @param string $namespaceName
     *
     * @return boolean
     */
    public function hasNamespace($namespaceName)
    {
        $namespaceName = strtolower($this->getUnquotedAssetName($namespaceName));

        return isset($this->namespaces[$namespaceName]);
    }

    /**
     * Does this schema have a table with the given name?
     *
     * @param string $tableName
     *
     * @return boolean
     */
    public function hasTable($tableName)
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);

        return isset($this->tables[$tableName]);
    }

    /**
     * Gets all table names, prefixed with a schema name, even the default one if present.
     *
     * @return array
     */
    public function getTableNames()
    {
        return array_keys($this->tables);
    }

    /**
     * @param string $sequenceName
     *
     * @return boolean
     */
    public function hasSequence($sequenceName)
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);

        return isset($this->sequences[$sequenceName]);
    }

    /**
     * @param string $sequenceName
     *
     * @return \Doctrine\DBAL\Schema\Sequence
     *
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function getSequence($sequenceName)
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);
        if (!$this->hasSequence($sequenceName)) {
            throw SchemaException::sequenceDoesNotExist($sequenceName);
        }

        return $this->sequences[$sequenceName];
    }

    /**
     * @return \Doctrine\DBAL\Schema\Sequence[]
     */
    public function getSequences()
    {
        return $this->sequences;
    }

    /**
     * Creates a new namespace.
     *
     * @param string $namespaceName The name of the namespace to create.
     *
     * @return \Doctrine\DBAL\Schema\Schema This schema instance.
     *
     * @throws SchemaException
     */
    public function createNamespace($namespaceName)
    {
        $unquotedNamespaceName = strtolower($this->getUnquotedAssetName($namespaceName));

        if (isset($this->namespaces[$unquotedNamespaceName])) {
            throw SchemaException::namespaceAlreadyExists($unquotedNamespaceName);
        }

        $this->namespaces[$unquotedNamespaceName] = $namespaceName;

        return $this;
    }

    /**
     * Creates a new table.
     *
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function createTable($tableName)
    {
        $table = new Table($tableName);
        $this->addTable($table);

        foreach ($this->schemaConfig->getDefaultTableOptions() as $name => $value) {
            $table->addOption($name, $value);
        }

        return $table;
    }

    /**
     * Renames a table.
     *
     * @param string $oldTableName
     * @param string $newTableName
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function renameTable($oldTableName, $newTableName)
    {
        $table = $this->getTable($oldTableName);
        $table->setName($newTableName);

        $this->dropTable($oldTableName);
        $this->addTable($table);

        return $this;
    }

    /**
     * Drops a table from the schema.
     *
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function dropTable($tableName)
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);
        $this->getTable($tableName);
        unset($this->tables[$tableName]);

        return $this;
    }

    /**
     * Creates a new sequence.
     *
     * @param string  $sequenceName
     * @param integer $allocationSize
     * @param integer $initialValue
     *
     * @return \Doctrine\DBAL\Schema\Sequence
     */
    public function createSequence($sequenceName, $allocationSize=1, $initialValue=1)
    {
        $seq = new Sequence($sequenceName, $allocationSize, $initialValue);
        $this->addSequence($seq);

        return $seq;
    }

    /**
     * @param string $sequenceName
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function dropSequence($sequenceName)
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);
        unset($this->sequences[$sequenceName]);

        return $this;
    }

    /**
     * Returns an array of necessary SQL queries to create the schema on the given platform.
     *
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return array
     */
    public function toSql(AbstractPlatform $platform)
    {
        $sqlCollector = new CreateSchemaSqlCollector($platform);
        $this->visit($sqlCollector);

        return $sqlCollector->getQueries();
    }

    /**
     * Return an array of necessary SQL queries to drop the schema on the given platform.
     *
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return array
     */
    public function toDropSql(AbstractPlatform $platform)
    {
        $dropSqlCollector = new DropSchemaSqlCollector($platform);
        $this->visit($dropSqlCollector);

        return $dropSqlCollector->getQueries();
    }

    /**
     * @param \Doctrine\DBAL\Schema\Schema              $toSchema
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return array
     */
    public function getMigrateToSql(Schema $toSchema, AbstractPlatform $platform)
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($this, $toSchema);

        return $schemaDiff->toSql($platform);
    }

    /**
     * @param \Doctrine\DBAL\Schema\Schema              $fromSchema
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return array
     */
    public function getMigrateFromSql(Schema $fromSchema, AbstractPlatform $platform)
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($fromSchema, $this);

        return $schemaDiff->toSql($platform);
    }

    /**
     * @param \Doctrine\DBAL\Schema\Visitor\Visitor $visitor
     *
     * @return void
     */
    public function visit(Visitor $visitor)
    {
        $visitor->acceptSchema($this);

        if ($visitor instanceof NamespaceVisitor) {
            foreach ($this->namespaces as $namespace) {
                $visitor->acceptNamespace($namespace);
            }
        }

        foreach ($this->tables as $table) {
            $table->visit($visitor);
        }

        foreach ($this->sequences as $sequence) {
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
        foreach ($this->tables as $k => $table) {
            $this->tables[$k] = clone $table;
        }
        foreach ($this->sequences as $k => $sequence) {
            $this->sequences[$k] = clone $sequence;
        }
    }
}

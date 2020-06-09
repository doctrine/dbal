<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\NamespaceAlreadyExists;
use Doctrine\DBAL\Schema\Exception\SequenceAlreadyExists;
use Doctrine\DBAL\Schema\Exception\SequenceDoesNotExist;
use Doctrine\DBAL\Schema\Exception\TableAlreadyExists;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector;
use Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector;
use Doctrine\DBAL\Schema\Visitor\NamespaceVisitor;
use Doctrine\DBAL\Schema\Visitor\Visitor;

use function array_keys;
use function strpos;
use function strtolower;

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
 */
class Schema extends AbstractAsset
{
    /**
     * The namespaces in this schema.
     *
     * @var array<string, string>
     */
    private $namespaces = [];

    /** @var array<string, Table> */
    protected $_tables = [];

    /** @var array<string, Sequence> */
    protected $_sequences = [];

    /** @var SchemaConfig */
    protected $_schemaConfig;

    /**
     * @param array<Table>    $tables
     * @param array<Sequence> $sequences
     * @param array<string>   $namespaces
     */
    public function __construct(
        array $tables = [],
        array $sequences = [],
        ?SchemaConfig $schemaConfig = null,
        array $namespaces = []
    ) {
        if ($schemaConfig === null) {
            $schemaConfig = new SchemaConfig();
        }

        $this->_schemaConfig = $schemaConfig;
        $this->_setName($schemaConfig->getName() ?? 'public');

        foreach ($namespaces as $namespace) {
            $this->createNamespace($namespace);
        }

        foreach ($tables as $table) {
            $this->_addTable($table);
        }

        foreach ($sequences as $sequence) {
            $this->_addSequence($sequence);
        }
    }

    public function hasExplicitForeignKeyIndexes(): bool
    {
        return $this->_schemaConfig->hasExplicitForeignKeyIndexes();
    }

    /**
     * @throws SchemaException
     */
    protected function _addTable(Table $table): void
    {
        $namespaceName = $table->getNamespaceName();
        $tableName     = $table->getFullQualifiedName($this->getName());

        if (isset($this->_tables[$tableName])) {
            throw TableAlreadyExists::new($tableName);
        }

        if (
            $namespaceName !== null
            && ! $table->isInDefaultNamespace($this->getName())
            && ! $this->hasNamespace($namespaceName)
        ) {
            $this->createNamespace($namespaceName);
        }

        $this->_tables[$tableName] = $table;
        $table->setSchemaConfig($this->_schemaConfig);
    }

    /**
     * @throws SchemaException
     */
    protected function _addSequence(Sequence $sequence): void
    {
        $namespaceName = $sequence->getNamespaceName();
        $seqName       = $sequence->getFullQualifiedName($this->getName());

        if (isset($this->_sequences[$seqName])) {
            throw SequenceAlreadyExists::new($seqName);
        }

        if (
            $namespaceName !== null
            && ! $sequence->isInDefaultNamespace($this->getName())
            && ! $this->hasNamespace($namespaceName)
        ) {
            $this->createNamespace($namespaceName);
        }

        $this->_sequences[$seqName] = $sequence;
    }

    /**
     * Returns the namespaces of this schema.
     *
     * @return array<string, string> A list of namespace names.
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Gets all tables of this schema.
     *
     * @return array<string, Table>
     */
    public function getTables(): array
    {
        return $this->_tables;
    }

    /**
     * @throws SchemaException
     */
    public function getTable(string $tableName): Table
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);
        if (! isset($this->_tables[$tableName])) {
            throw TableDoesNotExist::new($tableName);
        }

        return $this->_tables[$tableName];
    }

    private function getFullQualifiedAssetName(string $name): string
    {
        $name = $this->getUnquotedAssetName($name);

        if (strpos($name, '.') === false) {
            $name = $this->getName() . '.' . $name;
        }

        return strtolower($name);
    }

    /**
     * Returns the unquoted representation of a given asset name.
     */
    private function getUnquotedAssetName(string $assetName): string
    {
        if ($this->isIdentifierQuoted($assetName)) {
            return $this->trimQuotes($assetName);
        }

        return $assetName;
    }

    /**
     * Does this schema have a namespace with the given name?
     */
    public function hasNamespace(string $namespaceName): bool
    {
        $namespaceName = strtolower($this->getUnquotedAssetName($namespaceName));

        return isset($this->namespaces[$namespaceName]);
    }

    /**
     * Does this schema have a table with the given name?
     */
    public function hasTable(string $tableName): bool
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);

        return isset($this->_tables[$tableName]);
    }

    /**
     * Gets all table names, prefixed with a schema name, even the default one if present.
     *
     * @return array<int, string>
     */
    public function getTableNames(): array
    {
        return array_keys($this->_tables);
    }

    public function hasSequence(string $sequenceName): bool
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);

        return isset($this->_sequences[$sequenceName]);
    }

    /**
     * @throws SchemaException
     */
    public function getSequence(string $sequenceName): Sequence
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);
        if (! $this->hasSequence($sequenceName)) {
            throw SequenceDoesNotExist::new($sequenceName);
        }

        return $this->_sequences[$sequenceName];
    }

    /**
     * @return array<string, Sequence>
     */
    public function getSequences(): array
    {
        return $this->_sequences;
    }

    /**
     * Creates a new namespace.
     *
     * @return $this
     *
     * @throws SchemaException
     */
    public function createNamespace(string $namespaceName): self
    {
        $unquotedNamespaceName = strtolower($this->getUnquotedAssetName($namespaceName));

        if (isset($this->namespaces[$unquotedNamespaceName])) {
            throw NamespaceAlreadyExists::new($unquotedNamespaceName);
        }

        $this->namespaces[$unquotedNamespaceName] = $namespaceName;

        return $this;
    }

    /**
     * Creates a new table.
     */
    public function createTable(string $tableName): Table
    {
        $table = new Table($tableName);
        $this->_addTable($table);

        foreach ($this->_schemaConfig->getDefaultTableOptions() as $name => $value) {
            $table->addOption($name, $value);
        }

        return $table;
    }

    /**
     * Renames a table.
     *
     * @return $this
     */
    public function renameTable(string $oldTableName, string $newTableName): self
    {
        $table = $this->getTable($oldTableName);
        $table->_setName($newTableName);

        $this->dropTable($oldTableName);
        $this->_addTable($table);

        return $this;
    }

    /**
     * Drops a table from the schema.
     *
     * @return $this
     */
    public function dropTable(string $tableName): self
    {
        $tableName = $this->getFullQualifiedAssetName($tableName);
        $this->getTable($tableName);
        unset($this->_tables[$tableName]);

        return $this;
    }

    /**
     * Creates a new sequence.
     */
    public function createSequence(string $sequenceName, int $allocationSize = 1, int $initialValue = 1): Sequence
    {
        $seq = new Sequence($sequenceName, $allocationSize, $initialValue);
        $this->_addSequence($seq);

        return $seq;
    }

    /**
     * @return $this
     */
    public function dropSequence(string $sequenceName): self
    {
        $sequenceName = $this->getFullQualifiedAssetName($sequenceName);
        unset($this->_sequences[$sequenceName]);

        return $this;
    }

    /**
     * Returns an array of necessary SQL queries to create the schema on the given platform.
     *
     * @return array<int, string>
     */
    public function toSql(AbstractPlatform $platform): array
    {
        $sqlCollector = new CreateSchemaSqlCollector($platform);
        $this->visit($sqlCollector);

        return $sqlCollector->getQueries();
    }

    /**
     * Return an array of necessary SQL queries to drop the schema on the given platform.
     *
     * @return array<int, string>
     */
    public function toDropSql(AbstractPlatform $platform): array
    {
        $dropSqlCollector = new DropSchemaSqlCollector($platform);
        $this->visit($dropSqlCollector);

        return $dropSqlCollector->getQueries();
    }

    /**
     * @return array<int, string>
     */
    public function getMigrateToSql(Schema $toSchema, AbstractPlatform $platform): array
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($this, $toSchema);

        return $schemaDiff->toSql($platform);
    }

    /**
     * @return array<int, string>
     */
    public function getMigrateFromSql(Schema $fromSchema, AbstractPlatform $platform): array
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($fromSchema, $this);

        return $schemaDiff->toSql($platform);
    }

    public function visit(Visitor $visitor): void
    {
        $visitor->acceptSchema($this);

        if ($visitor instanceof NamespaceVisitor) {
            foreach ($this->namespaces as $namespace) {
                $visitor->acceptNamespace($namespace);
            }
        }

        foreach ($this->_tables as $table) {
            $table->visit($visitor);
        }

        foreach ($this->_sequences as $sequence) {
            $sequence->visit($visitor);
        }
    }

    /**
     * Cloning a Schema triggers a deep clone of all related assets.
     */
    public function __clone()
    {
        foreach ($this->_tables as $k => $table) {
            $this->_tables[$k] = clone $table;
        }

        foreach ($this->_sequences as $k => $sequence) {
            $this->_sequences[$k] = clone $sequence;
        }
    }
}

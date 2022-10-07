<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\NamespaceAlreadyExists;
use Doctrine\DBAL\Schema\Exception\SequenceAlreadyExists;
use Doctrine\DBAL\Schema\Exception\SequenceDoesNotExist;
use Doctrine\DBAL\Schema\Exception\TableAlreadyExists;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\SQL\Builder\CreateSchemaObjectsSQLBuilder;
use Doctrine\DBAL\SQL\Builder\DropSchemaObjectsSQLBuilder;

use function array_values;
use function str_contains;
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
    private array $namespaces = [];

    /** @var array<string, Table> */
    protected array $_tables = [];

    /** @var array<string, Sequence> */
    protected array $_sequences = [];

    protected SchemaConfig $_schemaConfig;

    /**
     * @param array<Table>    $tables
     * @param array<Sequence> $sequences
     * @param array<string>   $namespaces
     */
    public function __construct(
        array $tables = [],
        array $sequences = [],
        ?SchemaConfig $schemaConfig = null,
        array $namespaces = [],
    ) {
        $schemaConfig ??= new SchemaConfig();

        $this->_schemaConfig = $schemaConfig;

        $name = $schemaConfig->getName();

        if ($name !== null) {
            $this->_setName($name);
        }

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

    protected function _addTable(Table $table): void
    {
        $namespaceName = $table->getNamespaceName();
        $tableName     = $this->normalizeName($table);

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

    protected function _addSequence(Sequence $sequence): void
    {
        $namespaceName = $sequence->getNamespaceName();
        $seqName       = $this->normalizeName($sequence);

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
     * @return list<string> A list of namespace names.
     */
    public function getNamespaces(): array
    {
        return array_values($this->namespaces);
    }

    /**
     * Gets all tables of this schema.
     *
     * @return list<Table>
     */
    public function getTables(): array
    {
        return array_values($this->_tables);
    }

    public function getTable(string $name): Table
    {
        $name = $this->getFullQualifiedAssetName($name);
        if (! isset($this->_tables[$name])) {
            throw TableDoesNotExist::new($name);
        }

        return $this->_tables[$name];
    }

    private function getFullQualifiedAssetName(string $name): string
    {
        $name = $this->getUnquotedAssetName($name);

        if (! str_contains($name, '.')) {
            $name = $this->getName() . '.' . $name;
        }

        return strtolower($name);
    }

    /**
     * The normalized name is qualified and lower-cased. Lower-casing is
     * actually wrong, but we have to do it to keep our sanity. If you are
     * using database objects that only differentiate in the casing (FOO vs
     * Foo) then you will NOT be able to use Doctrine Schema abstraction.
     *
     * Every non-namespaced element is prefixed with this schema name.
     */
    private function normalizeName(AbstractAsset $asset): string
    {
        $name = $asset->getName();

        if ($asset->getNamespaceName() === null) {
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
    public function hasNamespace(string $name): bool
    {
        $name = strtolower($this->getUnquotedAssetName($name));

        return isset($this->namespaces[$name]);
    }

    /**
     * Does this schema have a table with the given name?
     */
    public function hasTable(string $name): bool
    {
        $name = $this->getFullQualifiedAssetName($name);

        return isset($this->_tables[$name]);
    }

    public function hasSequence(string $name): bool
    {
        $name = $this->getFullQualifiedAssetName($name);

        return isset($this->_sequences[$name]);
    }

    public function getSequence(string $name): Sequence
    {
        $name = $this->getFullQualifiedAssetName($name);
        if (! $this->hasSequence($name)) {
            throw SequenceDoesNotExist::new($name);
        }

        return $this->_sequences[$name];
    }

    /** @return list<Sequence> */
    public function getSequences(): array
    {
        return array_values($this->_sequences);
    }

    /**
     * Creates a new namespace.
     *
     * @return $this
     */
    public function createNamespace(string $name): self
    {
        $unquotedName = strtolower($this->getUnquotedAssetName($name));

        if (isset($this->namespaces[$unquotedName])) {
            throw NamespaceAlreadyExists::new($unquotedName);
        }

        $this->namespaces[$unquotedName] = $name;

        return $this;
    }

    /**
     * Creates a new table.
     */
    public function createTable(string $name): Table
    {
        $table = new Table($name);
        $this->_addTable($table);

        foreach ($this->_schemaConfig->getDefaultTableOptions() as $option => $value) {
            $table->addOption($option, $value);
        }

        return $table;
    }

    /**
     * Renames a table.
     *
     * @return $this
     */
    public function renameTable(string $oldName, string $newName): self
    {
        $table = $this->getTable($oldName);
        $table->_setName($newName);

        $this->dropTable($oldName);
        $this->_addTable($table);

        return $this;
    }

    /**
     * Drops a table from the schema.
     *
     * @return $this
     */
    public function dropTable(string $name): self
    {
        $name = $this->getFullQualifiedAssetName($name);
        $this->getTable($name);
        unset($this->_tables[$name]);

        return $this;
    }

    /**
     * Creates a new sequence.
     */
    public function createSequence(string $name, int $allocationSize = 1, int $initialValue = 1): Sequence
    {
        $seq = new Sequence($name, $allocationSize, $initialValue);
        $this->_addSequence($seq);

        return $seq;
    }

    /** @return $this */
    public function dropSequence(string $name): self
    {
        $name = $this->getFullQualifiedAssetName($name);
        unset($this->_sequences[$name]);

        return $this;
    }

    /**
     * Returns an array of necessary SQL queries to create the schema on the given platform.
     *
     * @return list<string>
     *
     * @throws Exception
     */
    public function toSql(AbstractPlatform $platform): array
    {
        $builder = new CreateSchemaObjectsSQLBuilder($platform);

        return $builder->buildSQL($this);
    }

    /**
     * Return an array of necessary SQL queries to drop the schema on the given platform.
     *
     * @return list<string>
     */
    public function toDropSql(AbstractPlatform $platform): array
    {
        $builder = new DropSchemaObjectsSQLBuilder($platform);

        return $builder->buildSQL($this);
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

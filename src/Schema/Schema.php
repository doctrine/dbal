<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\NamespaceAlreadyExists;
use Doctrine\DBAL\Schema\Exception\SequenceAlreadyExists;
use Doctrine\DBAL\Schema\Exception\SequenceDoesNotExist;
use Doctrine\DBAL\Schema\Exception\TableAlreadyExists;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\SQL\Builder\CreateSchemaObjectsSQLBuilder;
use Doctrine\DBAL\SQL\Builder\DropSchemaObjectsSQLBuilder;

use function array_values;
use function count;
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
 * Objects in a schema can be referenced by unqualified names or qualified
 * names but not both. Whether a given schema uses qualified or unqualified
 * names is determined at runtime by the presence of objects with unqualified
 * names and namespaces.
 *
 * The abstraction layer that covers a PostgreSQL schema is the namespace of a
 * database object (asset). A schema can have a name, which will be used as
 * default namespace for the unqualified database objects that are created in
 * the schema. If a schema uses qualified names and has a name, unqualified
 * names will be resolved against the corresponding namespace.
 *
 * In the case of MySQL where cross-database queries are allowed this leads to
 * databases being "misinterpreted" as namespaces. This is intentional, however
 * the CREATE/DROP SQL visitors will just filter this queries and do not
 * execute them. Only the queries for the currently connected database are
 * executed.
 */
class Schema
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
     * The default namespace name that the schema will use as a qualifier to resolve unqualified names.
     *
     * The name is assumed to be always set in its original case and thus will be represented as a quoted identifier.
     *
     * @var ?non-empty-string
     */
    private ?string $defaultNamespaceName;

    /**
     * Indicates whether the schema uses unqualified names for its objects. Once this flag is set to true, it won't be
     * unset even after the objects with unqualified names have been dropped from the schema.
     */
    private bool $usesUnqualifiedNames = false;

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

        $this->defaultNamespaceName = $schemaConfig->getName();

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
        $resolvedName = $this->resolveName($table->getObjectName());

        $key = $this->getKeyFromResolvedName($resolvedName);

        if (isset($this->_tables[$key])) {
            throw TableAlreadyExists::new($resolvedName->toString());
        }

        $this->registerQualifier($resolvedName->getQualifier());

        $this->_tables[$key] = $table;
    }

    protected function _addSequence(Sequence $sequence): void
    {
        $resolvedName = $this->resolveName($sequence->getObjectName());

        $key = $this->getKeyFromResolvedName($resolvedName);

        if (isset($this->_sequences[$key])) {
            throw SequenceAlreadyExists::new($resolvedName->toString());
        }

        $this->registerQualifier($resolvedName->getQualifier());

        $this->_sequences[$key] = $sequence;
    }

    private function registerQualifier(?Identifier $qualifier): void
    {
        if ($qualifier === null) {
            $this->usesUnqualifiedNames = true;

            return;
        }

        $namespaceName = $qualifier->getValue();

        if ($namespaceName === $this->defaultNamespaceName) {
            return;
        }

        if ($this->hasNamespace($namespaceName)) {
            return;
        }

        $this->createNamespace($namespaceName);
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
        $key = $this->getKeyFromName($name);
        if (! isset($this->_tables[$key])) {
            throw TableDoesNotExist::new($name);
        }

        return $this->_tables[$key];
    }

    /**
     * Returns the key that will be used to store the given object in a collection of such objects based on its name.
     *
     * If the schema uses unqualified names, the object name must be unqualified. If the schema uses qualified names,
     * the object name must be qualified.
     *
     * The resulting key is the lower-cased full object name. Lower-casing is
     * actually wrong, but we have to do it to keep our sanity. If you are
     * using database objects that only differentiate in the casing (FOO vs
     * Foo) then you will NOT be able to use Doctrine Schema abstraction.
     */
    private function getKeyFromResolvedName(OptionallyQualifiedName $name): string
    {
        $key       = $name->getUnqualifiedName()->getValue();
        $qualifier = $name->getQualifier();

        if ($qualifier !== null) {
            if ($this->usesUnqualifiedNames) {
                throw ImproperlyQualifiedName::fromQualifiedName($name);
            }

            $key = $qualifier->getValue() . '.' . $key;
        } elseif (count($this->namespaces) > 0) {
            throw ImproperlyQualifiedName::fromUnqualifiedName($name);
        }

        return strtolower($key);
    }

    /**
     * Returns the key that will be used to store the given object with the given name in a collection of such objects.
     *
     * If the schema configuration has the default namespace, an unqualified name will be resolved to qualified against
     * that namespace.
     */
    private function getKeyFromName(string $input): string
    {
        $name = $this->parseOptionallyQualifiedName($input);

        return $this->getKeyFromResolvedName(
            $this->resolveName($name),
        );
    }

    /**
     * Resolves the qualified or unqualified name against the current schema name and returns a qualified name.
     */
    private function resolveName(OptionallyQualifiedName $name): OptionallyQualifiedName
    {
        if ($name->getQualifier() === null && $this->defaultNamespaceName !== null) {
            return new OptionallyQualifiedName(
                $name->getUnqualifiedName(),
                Identifier::quoted($this->defaultNamespaceName),
            );
        }

        return $name;
    }

    /**
     * Does this schema have a namespace with the given name?
     */
    public function hasNamespace(string $name): bool
    {
        $key = $this->getNamespaceKey($name);

        return isset($this->namespaces[$key]);
    }

    /**
     * Does this schema have a table with the given name?
     */
    public function hasTable(string $name): bool
    {
        $key = $this->getKeyFromName($name);

        return isset($this->_tables[$key]);
    }

    public function hasSequence(string $name): bool
    {
        $key = $this->getKeyFromName($name);

        return isset($this->_sequences[$key]);
    }

    public function getSequence(string $name): Sequence
    {
        $key = $this->getKeyFromName($name);
        if (! isset($this->_sequences[$key])) {
            throw SequenceDoesNotExist::new($name);
        }

        return $this->_sequences[$key];
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
        $key = $this->getNamespaceKey($name);

        if (isset($this->namespaces[$key])) {
            throw NamespaceAlreadyExists::new($name);
        }

        $this->namespaces[$key] = $name;

        return $this;
    }

    /**
     * Returns the key that will be used to store the given namespace name in the collection of namespaces.
     */
    private function getNamespaceKey(string $name): string
    {
        $parser = Parsers::getUnqualifiedNameParser();

        try {
            $parsedName = $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }

        return strtolower($parsedName->getIdentifier()->getValue());
    }

    /**
     * Creates a new table.
     */
    public function createTable(string $name): Table
    {
        $parsedName = $this->parseOptionallyQualifiedName($name);

        $table = Table::editor()
            ->setName($parsedName)
            ->setOptions($this->_schemaConfig->getDefaultTableOptions())
            ->setConfiguration($this->_schemaConfig->toTableConfiguration())
            ->create();

        $this->_addTable($table);

        return $table;
    }

    /**
     * Renames a table.
     *
     * @return $this
     */
    public function renameTable(string $oldName, string $newName): self
    {
        $parsedName = $this->parseOptionallyQualifiedName($newName);

        $table = $this->getTable($oldName)
            ->edit()
            ->setName($parsedName)
            ->create();

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
        $key = $this->getKeyFromName($name);
        if (! isset($this->_tables[$key])) {
            throw TableDoesNotExist::new($name);
        }

        unset($this->_tables[$key]);

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
        $key = $this->getKeyFromName($name);
        unset($this->_sequences[$key]);

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

    private function parseOptionallyQualifiedName(string $input): OptionallyQualifiedName
    {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            return $parser->parse($input);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($input, $e);
        }
    }
}

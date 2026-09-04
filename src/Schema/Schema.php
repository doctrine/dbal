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
use Doctrine\DBAL\Schema\Name\Parser\UnqualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\SQL\Builder\CreateSchemaObjectsSQLBuilder;
use Doctrine\DBAL\SQL\Builder\DropSchemaObjectsSQLBuilder;
use Doctrine\Deprecations\Deprecation;

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
 *
 * @final
 * @extends AbstractAsset<UnqualifiedName>
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
     * Indicates whether the schema uses unqualified names for its objects. Once this flag is set to true, it won't be
     * unset even after the objects with unqualified names have been dropped from the schema.
     */
    private bool $usesUnqualifiedNames = false;

    /**
     * @internal since doctrine/dbal 4.5. Use {@link Schema::editor()} to instantiate an editor
     *           and {@link SchemaEditor::create()} to create a schema.
     *
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
        if (count($namespaces) > 0) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7186',
                'Passing the $namespaces argument to the Schema constructor is deprecated.',
            );
        }

        $schemaConfig ??= new SchemaConfig();

        $this->_schemaConfig = $schemaConfig;

        $name = $schemaConfig->getName();

        parent::__construct($name ?? '');

        foreach ($namespaces as $namespace) {
            $this->createNamespace($namespace);
        }

        foreach ($tables as $table) {
            $table->setSchemaConfig($this->_schemaConfig);
            $this->_addTable($table);
        }

        foreach ($sequences as $sequence) {
            $this->_addSequence($sequence);
        }
    }

    /** @deprecated */
    public function getName(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6734',
            'Using Schema as AbstractAsset, including %s, is deprecated.',
            __METHOD__,
        );

        return parent::getName();
    }

    protected function getNameParser(): UnqualifiedNameParser
    {
        return Parsers::getUnqualifiedNameParser();
    }

    /**
     * The object representation of the name isn't used because {@see Schema} is not an {@see AbstractAsset}.
     *
     * This method implements the abstract method in the parent class and will be removed once {@see Schema} stops
     * extending {@see AbstractAsset}.
     */
    protected function setName(?Name $name): void
    {
    }

    protected function _addTable(Table $table): void
    {
        $resolvedName = $this->resolveName($table);

        $key = $this->getKeyFromResolvedName($resolvedName);

        if (isset($this->_tables[$key])) {
            throw TableAlreadyExists::new($resolvedName->getName());
        }

        $namespaceName = $resolvedName->getNamespaceName();

        if ($namespaceName !== null) {
            if (
                ! $table->isInDefaultNamespace($this->getName())
                && ! $this->hasNamespace($namespaceName)
            ) {
                $this->createNamespace($namespaceName);
            }
        } else {
            $this->usesUnqualifiedNames = true;
        }

        $this->_tables[$key] = $table;
    }

    protected function _addSequence(Sequence $sequence): void
    {
        $resolvedName = $this->resolveName($sequence);

        $key = $this->getKeyFromResolvedName($resolvedName);

        if (isset($this->_sequences[$key])) {
            throw SequenceAlreadyExists::new($resolvedName->getName());
        }

        $namespaceName = $resolvedName->getNamespaceName();

        if ($namespaceName !== null) {
            if (
                ! $sequence->isInDefaultNamespace($this->getName())
                && ! $this->hasNamespace($namespaceName)
            ) {
                $this->createNamespace($namespaceName);
            }
        } else {
            $this->usesUnqualifiedNames = true;
        }

        $this->_sequences[$key] = $sequence;
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
     *
     * @param AbstractAsset<N> $asset
     *
     * @template N of Name
     */
    private function getKeyFromResolvedName(AbstractAsset $asset): string
    {
        $key = $asset->getName();

        if ($asset->getNamespaceName() !== null) {
            if ($this->usesUnqualifiedNames) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6677#user-content-qualified-names',
                    'Using qualified names to create or reference objects in a schema that uses unqualified '
                        . 'names is deprecated.',
                );
            }

            $key = $this->getName() . '.' . $key;
        } elseif (count($this->namespaces) > 0) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6677#user-content-unqualified-names',
                'Using unqualified names to create or reference objects in a schema that uses qualified '
                    . 'names and lacks a default namespace configuration is deprecated.',
            );
        }

        return strtolower($key);
    }

    /**
     * Returns the key that will be used to store the given object with the given name in a collection of such objects.
     *
     * If the schema configuration has the default namespace, an unqualified name will be resolved to qualified against
     * that namespace.
     */
    private function getKeyFromName(string $name): string
    {
        return $this->getKeyFromResolvedName($this->resolveName(new Identifier($name)));
    }

    /**
     * Resolves the qualified or unqualified name against the current schema name and returns a qualified name.
     *
     * @param AbstractAsset<N> $asset A database object with optionally qualified name.
     *
     * @template N of Name
     */
    private function resolveName(AbstractAsset $asset): AbstractAsset
    {
        if ($asset->getNamespaceName() === null) {
            $defaultNamespaceName = $this->getName();

            if ($defaultNamespaceName !== '') {
                return new Identifier($defaultNamespaceName . '.' . $asset->getName());
            }
        }

        return $asset;
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
     * @deprecated The schema automatically derives namespaces from the names of its tables and sequences.
     *             Creating empty namespaces is deprecated.
     *
     * @return $this
     */
    public function createNamespace(string $name): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7186',
            '%s is deprecated. The schema automatically derives namespaces from the names of its tables and'
                . ' sequences. Creating empty namespaces is deprecated.',
            __METHOD__,
        );

        $unquotedName = strtolower($this->getUnquotedAssetName($name));

        if (isset($this->namespaces[$unquotedName])) {
            throw NamespaceAlreadyExists::new($unquotedName);
        }

        $this->namespaces[$unquotedName] = $name;

        return $this;
    }

    /**
     * Creates a new table.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see SchemaEditor::addTable()} instead.
     */
    public function createTable(string $name): Table
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7373',
            '%s is deprecated. Use Schema::edit() and SchemaEditor::addTable() instead.',
            __METHOD__,
        );

        $table = new Table(
            $name,
            [],
            [],
            [],
            [],
            [],
            $this->_schemaConfig->toTableConfiguration(),
            null,
            [],
        );
        $table->setTypeRegistry($this->_schemaConfig->getTypeRegistry());
        $this->_addTable($table);

        foreach ($this->_schemaConfig->getDefaultTableOptions() as $option => $value) {
            $table->addOption($option, $value);
        }

        return $table;
    }

    /**
     * Renames a table.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see SchemaEditor::renameTable()} instead.
     *
     * @return $this
     */
    public function renameTable(string $oldName, string $newName): self
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7373',
            '%s is deprecated. Use Schema::edit() and SchemaEditor::renameTable() instead.',
            __METHOD__,
        );

        $table = $this->getTable($oldName);

        $identifier = new Identifier($newName);

        $table->_name      = $identifier->_name;
        $table->_namespace = $identifier->_namespace;
        $table->_quoted    = $identifier->_quoted;

        $this->dropTable($oldName);
        $this->_addTable($table);

        return $this;
    }

    /**
     * Drops a table from the schema.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see SchemaEditor::dropTable()} instead.
     *
     * @return $this
     */
    public function dropTable(string $name): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7373',
            '%s is deprecated. Use Schema::edit() and SchemaEditor::dropTable() instead.',
            __METHOD__,
        );

        $key = $this->getKeyFromName($name);
        if (! isset($this->_tables[$key])) {
            throw TableDoesNotExist::new($name);
        }

        unset($this->_tables[$key]);

        return $this;
    }

    /**
     * Creates a new sequence.
     *
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see SchemaEditor::addSequence()} instead.
     */
    public function createSequence(string $name, int $allocationSize = 1, int $initialValue = 1): Sequence
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7373',
            '%s is deprecated. Use Schema::edit() and SchemaEditor::addSequence() instead.',
            __METHOD__,
        );

        $seq = new Sequence($name, $allocationSize, $initialValue);
        $this->_addSequence($seq);

        return $seq;
    }

    /**
     * @deprecated since doctrine/dbal 4.5. Use {@see edit()} and {@see SchemaEditor::dropSequence()} instead.
     *
     * @return $this
     */
    public function dropSequence(string $name): self
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7373',
            '%s is deprecated. Use Schema::edit() and SchemaEditor::dropSequence() instead.',
            __METHOD__,
        );

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
     * Instantiates a new schema editor.
     */
    public static function editor(): SchemaEditor
    {
        return new SchemaEditor();
    }

    /**
     * Instantiates a new schema editor seeded with this schema's tables, sequences, and configuration.
     */
    public function edit(): SchemaEditor
    {
        return self::editor()
            ->setDefaultNamespace($this->_schemaConfig->getName())
            ->setTables(...$this->getTables())
            ->setSequences(...$this->getSequences());
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

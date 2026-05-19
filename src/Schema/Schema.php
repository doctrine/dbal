<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\SequenceDoesNotExist;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\SQL\Builder\CreateSchemaObjectsSQLBuilder;
use Doctrine\DBAL\SQL\Builder\DropSchemaObjectsSQLBuilder;

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
final readonly class Schema
{
    /**
     * @internal Use {@link Schema::editor()} to instantiate an editor and {@link SchemaEditor::create()}
     *           to create a schema.
     *
     * @param ?non-empty-string $defaultNamespaceName
     */
    public function __construct(
        private ReadableSchemaObjects $objects,
        private ?string $defaultNamespaceName = null,
    ) {
    }

    /**
     * Returns the namespaces of this schema.
     *
     * @return list<non-empty-string> A list of namespace names.
     */
    public function getNamespaces(): array
    {
        return $this->objects->getNamespaces();
    }

    /**
     * Gets all tables of this schema.
     *
     * @return list<Table>
     */
    public function getTables(): array
    {
        return $this->objects->getTables();
    }

    public function getTable(string $name): Table
    {
        $table = $this->objects->getTable($this->parseOptionallyQualifiedName($name));

        if ($table === null) {
            throw TableDoesNotExist::new($name);
        }

        return $table;
    }

    /**
     * Does this schema have a namespace with the given name?
     */
    public function hasNamespace(string $name): bool
    {
        return $this->objects->hasNamespace($this->parseUnqualifiedName($name));
    }

    /**
     * Does this schema have a table with the given name?
     */
    public function hasTable(string $name): bool
    {
        return $this->objects->hasTable($this->parseOptionallyQualifiedName($name));
    }

    public function hasSequence(string $name): bool
    {
        return $this->objects->hasSequence($this->parseOptionallyQualifiedName($name));
    }

    public function getSequence(string $name): Sequence
    {
        $sequence = $this->objects->getSequence($this->parseOptionallyQualifiedName($name));

        if ($sequence === null) {
            throw SequenceDoesNotExist::new($name);
        }

        return $sequence;
    }

    /** @return list<Sequence> */
    public function getSequences(): array
    {
        return $this->objects->getSequences();
    }

    /**
     * Returns an array of necessary SQL queries to create the schema on the given platform.
     *
     * @return list<string>
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
     * Instantiates a new schema editor seeded with this schema's tables, sequences, and default namespace.
     */
    public function edit(): SchemaEditor
    {
        return self::editor()
            ->setDefaultNamespace($this->defaultNamespaceName)
            ->setTables(...$this->getTables())
            ->setSequences(...$this->getSequences());
    }

    private function parseUnqualifiedName(string $input): UnqualifiedName
    {
        $parser = Parsers::getUnqualifiedNameParser();

        try {
            return $parser->parse($input);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($input, $e);
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

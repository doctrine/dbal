<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLite;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;

/**
 * SQLite SchemaManager.
 *
 * @extends AbstractSchemaManager<SQLitePlatform>
 */
class SQLiteSchemaManager extends AbstractSchemaManager
{
    public function createForeignKey(ForeignKeyConstraint $foreignKey, string $table): void
    {
        $table = $this->introspectTableByStringName($table);

        $this->alterTable(new TableDiff($table, addedForeignKeys: [$foreignKey]));
    }

    public function dropForeignKey(string $name, string $table): void
    {
        $table = $this->introspectTableByStringName($table);

        $foreignKey = $table->getForeignKey($name);

        $this->alterTable(new TableDiff($table, droppedForeignKeys: [$foreignKey]));
    }

    /** @throws Exception */
    private function introspectTableByStringName(string $name): Table
    {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            $parsedName = $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }

        return $this->introspectTable($parsedName);
    }

    public function createComparator(ComparatorConfig $config = new ComparatorConfig()): Comparator
    {
        return new SQLite\Comparator($this->platform, $config);
    }
}

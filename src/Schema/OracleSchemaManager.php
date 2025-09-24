<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;

/**
 * Oracle Schema Manager.
 *
 * @extends AbstractSchemaManager<OraclePlatform>
 */
class OracleSchemaManager extends AbstractSchemaManager
{
    public function createDatabase(string $database): void
    {
        $statement = $this->platform->getCreateDatabaseSQL($database);

        $params = $this->connection->getParams();

        if (isset($params['password'])) {
            $statement .= ' IDENTIFIED BY ' . $this->connection->quoteSingleIdentifier($params['password']);
        }

        $this->connection->executeStatement($statement);

        $statement = 'GRANT DBA TO ' . $database;
        $this->connection->executeStatement($statement);
    }

    /** @throws Exception */
    private function dropAutoincrement(OptionallyQualifiedName $table): void
    {
        $sql = $this->platform->getDropAutoincrementSql($table);
        foreach ($sql as $query) {
            $this->connection->executeStatement($query);
        }
    }

    public function dropTable(string $name): void
    {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            $tableName = $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }

        try {
            $this->dropAutoincrement($tableName);
        } catch (DatabaseObjectNotFoundException) {
        }

        parent::dropTable($name);
    }
}

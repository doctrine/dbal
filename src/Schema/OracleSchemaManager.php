<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\OraclePlatform;

/**
 * Oracle Schema Manager.
 *
 * @extends AbstractSchemaManager<OraclePlatform>
 */
class OracleSchemaManager extends AbstractSchemaManager
{
    public function createDatabase(string $databaseName): void
    {
        $parsedName = $this->parseUnqualifiedName($databaseName);

        $statement = $this->platform->getCreateDatabaseSQL($databaseName);

        $params = $this->connection->getParams();

        if (isset($params['password'])) {
            $statement .= ' IDENTIFIED BY ' . $this->connection->quoteSingleIdentifier($params['password']);
        }

        $this->connection->executeStatement($statement);

        $statement = 'GRANT DBA TO ' . $parsedName->toSQL($this->platform);
        $this->connection->executeStatement($statement);
    }

    public function dropTable(string $tableName): void
    {
        $parsedName = $this->parseOptionallyQualifiedName($tableName);

        try {
            $sql = $this->platform->getDropAutoincrementSql($parsedName);
            foreach ($sql as $query) {
                $this->connection->executeStatement($query);
            }
        } catch (DatabaseObjectNotFoundException) {
        }

        parent::dropTable($tableName);
    }
}

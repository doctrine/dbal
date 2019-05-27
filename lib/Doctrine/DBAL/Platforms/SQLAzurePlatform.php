<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Table;

/**
 * Platform to ensure compatibility of Doctrine with SQL Azure
 *
 * On top of SQL Server 2008 the following functionality is added:
 *
 * - Create tables with the FEDERATED ON syntax.
 */
class SQLAzurePlatform extends SQLServerPlatform
{
    /**
     * {@inheritDoc}
     */
    public function getCreateTableSQL(Table $table, int $createFlags = self::CREATE_INDEXES) : array
    {
        $sql = parent::getCreateTableSQL($table, $createFlags);

        if ($table->hasOption('azure.federatedOnColumnName')) {
            $distributionName = $table->getOption('azure.federatedOnDistributionName');
            $columnName       = $table->getOption('azure.federatedOnColumnName');
            $stmt             = ' FEDERATED ON (' . $distributionName . ' = ' . $columnName . ')';

            $sql[0] .= $stmt;
        }

        return $sql;
    }
}

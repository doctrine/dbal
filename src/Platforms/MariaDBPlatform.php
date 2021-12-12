<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MariaDBKeywords;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\TableDiff;

use function array_diff_key;
use function array_merge;
use function count;
use function in_array;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB database platform of the oldest supported version.
 */
class MariaDBPlatform extends AbstractMySQLPlatform
{
    /**
     * {@inheritdoc}
     *
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        return 'LONGTEXT';
    }

    /**
     * {@inheritdoc}
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql       = [];
        $tableName = $diff->getName($this)->getQuotedName($this);

        foreach ($this->getRemainingForeignKeyConstraintsRequiringRenamedIndexes($diff) as $foreignKey) {
            if (in_array($foreignKey, $diff->changedForeignKeys, true)) {
                continue;
            }

            $sql[] = $this->getDropForeignKeySQL($foreignKey->getQuotedName($this), $tableName);
        }

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        return array_merge(
            parent::getPostAlterTableIndexForeignKeySQL($diff),
            $this->getPostAlterTableRenameIndexForeignKeySQL($diff)
        );
    }

    /**
     * @return list<string>
     */
    private function getPostAlterTableRenameIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql     = [];
        $newName = $diff->getNewName();

        if ($newName !== null) {
            $tableName = $newName->getQuotedName($this);
        } else {
            $tableName = $diff->getName($this)->getQuotedName($this);
        }

        foreach ($this->getRemainingForeignKeyConstraintsRequiringRenamedIndexes($diff) as $foreignKey) {
            if (in_array($foreignKey, $diff->changedForeignKeys, true)) {
                continue;
            }

            $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableName);
        }

        return $sql;
    }

    /**
     * Returns the remaining foreign key constraints that require one of the renamed indexes.
     *
     * "Remaining" here refers to the diff between the foreign keys currently defined in the associated
     * table and the foreign keys to be removed.
     *
     * @param TableDiff $diff The table diff to evaluate.
     *
     * @return ForeignKeyConstraint[]
     */
    private function getRemainingForeignKeyConstraintsRequiringRenamedIndexes(TableDiff $diff): array
    {
        if ($diff->fromTable === null || count($diff->renamedIndexes) === 0) {
            return [];
        }

        $foreignKeys = [];

        $remainingForeignKeys = array_diff_key(
            $diff->fromTable->getForeignKeys(),
            $diff->removedForeignKeys
        );

        foreach ($remainingForeignKeys as $foreignKey) {
            foreach ($diff->renamedIndexes as $index) {
                if ($foreignKey->intersectsIndexColumns($index)) {
                    $foreignKeys[] = $foreignKey;

                    break;
                }
            }
        }

        return $foreignKeys;
    }

    protected function createReservedKeywordsList(): KeywordList
    {
        return new MariaDBKeywords();
    }
}

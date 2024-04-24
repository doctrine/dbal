<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MySQL80Keywords;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 8.0 (8.0 GA) database platform.
 */
class MySQL80Platform extends MySQLPlatform
{
    protected function createReservedKeywordsList(): KeywordList
    {
        return new MySQL80Keywords();
    }

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return AbstractPlatform::createSelectSQLBuilder();
    }

    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $name    = $index->getQuotedName($this);
        $columns = $index->getColumns();

        if (count($columns) === 0) {
            throw new InvalidArgumentException(sprintf(
                'Incomplete or invalid index definition %s on table %s',
                $name,
                $table,
            ));
        }

        if ($index->isPrimary()) {
            return $this->getCreatePrimaryKeySQL($index, $table);
        }

        $query  = 'ALTER TABLE ' .$name . ' ADD' . $this->getCreateIndexSQLFlags($index) . 'INDEX ';
        $query .= ' (' . implode(', ', $index->getQuotedColumns($this)) . ')' . $this->getPartialIndexSQL($index);
        $query .= ', ALGORITHM=INPLACE, LOCK=NONE';

        return $query;
    }
}

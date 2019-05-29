<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use function is_array;

/**
 * Event Arguments used when SQL queries for creating table columns are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 */
class SchemaCreateTableColumnEventArgs extends SchemaEventArgs
{
    /** @var Column */
    private $column;

    /** @var Table */
    private $table;

    /** @var AbstractPlatform */
    private $platform;

    /** @var array<int, string> */
    private $sql = [];

    public function __construct(Column $column, Table $table, AbstractPlatform $platform)
    {
        $this->column   = $column;
        $this->table    = $table;
        $this->platform = $platform;
    }

    /**
     * @return Column
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return AbstractPlatform
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * @param string|string[] $sql
     *
     * @return \Doctrine\DBAL\Event\SchemaCreateTableColumnEventArgs
     */
    public function addSql($sql)
    {
        if (is_array($sql)) {
            foreach ($sql as $query) {
                $this->sql[] = $query;
            }
        } else {
            $this->sql[] = $sql;
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getSql()
    {
        return $this->sql;
    }
}

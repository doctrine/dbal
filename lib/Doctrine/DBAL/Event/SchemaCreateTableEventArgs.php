<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use function is_array;

/**
 * Event Arguments used when SQL queries for creating tables are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 */
class SchemaCreateTableEventArgs extends SchemaEventArgs
{
    /** @var Table */
    private $table;

    /** @var mixed[][] */
    private $columns;

    /** @var mixed[] */
    private $options;

    /** @var AbstractPlatform */
    private $platform;

    /** @var string[] */
    private $sql = [];

    /**
     * @param mixed[][] $columns
     * @param mixed[]   $options
     */
    public function __construct(Table $table, array $columns, array $options, AbstractPlatform $platform)
    {
        $this->table    = $table;
        $this->columns  = $columns;
        $this->options  = $options;
        $this->platform = $platform;
    }

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return mixed[][]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @return mixed[]
     */
    public function getOptions()
    {
        return $this->options;
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
     * @return \Doctrine\DBAL\Event\SchemaCreateTableEventArgs
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

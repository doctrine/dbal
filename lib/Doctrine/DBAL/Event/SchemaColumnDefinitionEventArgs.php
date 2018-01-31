<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

/**
 * Event Arguments used when the portable column definition is generated inside Doctrine\DBAL\Schema\AbstractSchemaManager.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaColumnDefinitionEventArgs extends SchemaEventArgs
{
    /**
     * @var \Doctrine\DBAL\Schema\Column|null
     */
    private $column = null;

    /**
     * Raw column data as fetched from the database.
     *
     * @var array
     */
    private $tableColumn;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $database;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @param array                     $tableColumn
     * @param string                    $table
     * @param string                    $database
     * @param \Doctrine\DBAL\Connection $connection
     */
    public function __construct(array $tableColumn, $table, $database, Connection $connection)
    {
        $this->tableColumn = $tableColumn;
        $this->table       = $table;
        $this->database    = $database;
        $this->connection  = $connection;
    }

    /**
     * Allows to clear the column which means the column will be excluded from
     * tables column list.
     *
     * @param null|\Doctrine\DBAL\Schema\Column $column
     *
     * @return \Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs
     */
    public function setColumn(Column $column = null)
    {
        $this->column = $column;

        return $this;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column|null
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @return array
     */
    public function getTableColumn()
    {
        return $this->tableColumn;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getDatabasePlatform()
    {
        return $this->connection->getDatabasePlatform();
    }
}

<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Index;

/**
 * Event Arguments used when the portable index definition is generated inside Doctrine\DBAL\Schema\AbstractSchemaManager.
 *
 * @link   www.doctrine-project.org
 */
class SchemaIndexDefinitionEventArgs extends SchemaEventArgs
{
    /** @var Index|null */
    private $_index = null;

    /**
     * Raw index data as fetched from the database.
     *
     * @var mixed[]
     */
    private $_tableIndex;

    /** @var string */
    private $_table;

    /** @var Connection */
    private $_connection;

    /**
     * @param mixed[] $tableIndex
     * @param string  $table
     */
    public function __construct(array $tableIndex, $table, Connection $connection)
    {
        $this->_tableIndex = $tableIndex;
        $this->_table      = $table;
        $this->_connection = $connection;
    }

    /**
     * Allows to clear the index which means the index will be excluded from tables index list.
     *
     * @return SchemaIndexDefinitionEventArgs
     */
    public function setIndex(?Index $index = null)
    {
        $this->_index = $index;

        return $this;
    }

    /**
     * @return Index|null
     */
    public function getIndex()
    {
        return $this->_index;
    }

    /**
     * @return mixed[]
     */
    public function getTableIndex()
    {
        return $this->_tableIndex;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * @return AbstractPlatform
     */
    public function getDatabasePlatform()
    {
        return $this->_connection->getDatabasePlatform();
    }
}

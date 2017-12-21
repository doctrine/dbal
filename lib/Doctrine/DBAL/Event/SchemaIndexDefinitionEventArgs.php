<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index;

/**
 * Event Arguments used when the portable index definition is generated inside Doctrine\DBAL\Schema\AbstractSchemaManager.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaIndexDefinitionEventArgs extends SchemaEventArgs
{
    /**
     * @var \Doctrine\DBAL\Schema\Index|null
     */
    private $_index = null;

    /**
     * Raw index data as fetched from the database.
     *
     * @var array
     */
    private $_tableIndex;

    /**
     * @var string
     */
    private $_table;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $_connection;

    /**
     * @param array                     $tableIndex
     * @param string                    $table
     * @param \Doctrine\DBAL\Connection $connection
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
     * @param null|\Doctrine\DBAL\Schema\Index $index
     *
     * @return SchemaIndexDefinitionEventArgs
     */
    public function setIndex(Index $index = null)
    {
        $this->_index = $index;

        return $this;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Index|null
     */
    public function getIndex()
    {
        return $this->_index;
    }

    /**
     * @return array
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
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getDatabasePlatform()
    {
        return $this->_connection->getDatabasePlatform();
    }
}

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
    private $index = null;

    /**
     * Raw index data as fetched from the database.
     *
     * @var array
     */
    private $tableIndex;

    /**
     * @var string
     */
    private $table;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @param array                     $tableIndex
     * @param string                    $table
     * @param \Doctrine\DBAL\Connection $connection
     */
    public function __construct(array $tableIndex, $table, Connection $connection)
    {
        $this->tableIndex = $tableIndex;
        $this->table      = $table;
        $this->connection = $connection;
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
        $this->index = $index;

        return $this;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Index|null
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return array
     */
    public function getTableIndex()
    {
        return $this->tableIndex;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
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

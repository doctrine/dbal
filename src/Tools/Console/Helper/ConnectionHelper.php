<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tools\Console\Helper;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Helper\Helper;

/**
 * Doctrine CLI Connection Helper.
 *
 * @deprecated use a ConnectionProvider instead.
 */
class ConnectionHelper extends Helper
{
    /**
     * The Doctrine database Connection.
     *
     * @var Connection
     */
    protected $_connection;

    /**
     * @param Connection $connection The Doctrine database Connection.
     */
    public function __construct(Connection $connection)
    {
        $this->_connection = $connection;
    }

    /**
     * Retrieves the Doctrine database Connection.
     */
    public function getConnection(): Connection
    {
        return $this->_connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'connection';
    }
}

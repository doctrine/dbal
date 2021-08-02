<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query;

use function assert;

/**
 * Base class for all errors detected in the driver.
 *
 * @psalm-immutable
 */
class DriverException extends Exception implements Driver\Exception
{
    /**
     * The query that triggered the exception, if any.
     */
    private ?Query $query = null;

    /**
     * @internal
     *
     * @param Driver\Exception $driverException The DBAL driver exception to chain.
     * @param Query|null       $query           The SQL query that triggered the exception, if any.
     */
    public function __construct(Driver\Exception $driverException, ?Query $query)
    {
        if ($query !== null) {
            $message = 'An exception occurred while executing a query: ' . $driverException->getMessage();
        } else {
            $message = 'An exception occurred in the driver: ' . $driverException->getMessage();
        }

        parent::__construct($message, $driverException->getCode(), $driverException);

        $this->query = $query;
    }

    public function getSQLState(): ?string
    {
        $previous = $this->getPrevious();
        assert($previous instanceof Driver\Exception);

        return $previous->getSQLState();
    }

    public function getQuery(): ?Query
    {
        return $this->query;
    }
}

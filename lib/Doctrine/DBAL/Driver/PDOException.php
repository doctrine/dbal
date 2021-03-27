<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\Deprecations\Deprecation;

/**
 * @deprecated Use {@link Exception} instead
 *
 * @psalm-immutable
 */
class PDOException extends \PDOException implements DriverException
{
    /**
     * The driver specific error code.
     *
     * @var int|string|null
     */
    private $errorCode;

    /**
     * The SQLSTATE of the driver.
     *
     * @var string|null
     */
    private $sqlState;

    /**
     * @param \PDOException $exception The PDO exception to wrap.
     */
    public function __construct(\PDOException $exception)
    {
        parent::__construct($exception->getMessage(), 0, $exception);

        $this->code      = $exception->getCode();
        $this->errorInfo = $exception->errorInfo;
        $this->errorCode = $exception->errorInfo[1] ?? $exception->getCode();
        $this->sqlState  = $exception->errorInfo[0] ?? $exception->getCode();
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorCode()
    {
        /** @psalm-suppress ImpureMethodCall */
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4112',
            'Driver\AbstractException::getErrorCode() is deprecated, use getSQLState() or getCode() instead.'
        );

        return $this->errorCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLState()
    {
        return $this->sqlState;
    }
}

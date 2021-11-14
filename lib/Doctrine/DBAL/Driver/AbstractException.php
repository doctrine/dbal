<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\Deprecations\Deprecation;
use Exception as BaseException;
use Throwable;

/**
 * Base implementation of the {@link Exception} interface.
 *
 * @internal
 *
 * @psalm-immutable
 */
abstract class AbstractException extends BaseException implements DriverException
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
     * @param string          $message   The driver error message.
     * @param string|null     $sqlState  The SQLSTATE the driver is in at the time the error occurred, if any.
     * @param int|string|null $errorCode The driver specific error code if any.
     */
    public function __construct($message, $sqlState = null, $errorCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->errorCode = $errorCode;
        $this->sqlState  = $sqlState;
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

<?php

namespace Doctrine\DBAL\Driver;

/**
 * Tiny wrapper for PDOException instances to implement the {@link DriverException} interface.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class PDOException extends \PDOException implements DriverException
{
    /**
     * The driver specific error code.
     *
     * @var integer|string|null
     */
    private $errorCode;

    /**
     * The SQLSTATE of the driver.
     *
     * @var string|null
     */
    private $sqlState;

    /**
     * Constructor.
     *
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

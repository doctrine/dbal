<?php

namespace Doctrine\DBAL\Driver;

/**
 * Abstract base implementation of the {@link DriverException} interface.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
abstract class AbstractDriverException extends \Exception implements DriverException
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
     * Constructor.
     *
     * @param string          $message   The driver error message.
     * @param string|null     $sqlState  The SQLSTATE the driver is in at the time the error occurred, if any.
     * @param int|string|null $errorCode The driver specific error code if any.
     */
    public function __construct($message, $sqlState = null, $errorCode = null)
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->sqlState  = $sqlState;
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

<?php

namespace Doctrine\DBAL\Driver;

/**
 * Contract for a driver exception.
 *
 * Driver exceptions provide the SQLSTATE of the driver
 * and the driver specific error code at the time the error occurred.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
interface DriverException extends \Throwable
{
    /**
     * Returns the driver specific error code if available.
     *
     * Returns null if no driver specific error code is available
     * for the error raised by the driver.
     *
     * @return integer|string|null
     */
    public function getErrorCode();

    /**
     * Returns the driver error message.
     *
     * @return string
     */
    public function getMessage();

    /**
     * Returns the SQLSTATE the driver was in at the time the error occurred.
     *
     * Returns null if the driver does not provide a SQLSTATE for the error occurred.
     *
     * @return string|null
     */
    public function getSQLState();
}

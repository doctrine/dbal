<?php

namespace Doctrine\DBAL\Driver;

/**
 * Contract for a driver that is capable of converting DBAL driver exceptions into standardized DBAL driver exceptions.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
interface ExceptionConverterDriver
{
    /**
     * Converts a given DBAL driver exception into a standardized DBAL driver exception.
     *
     * It evaluates the vendor specific error code and SQLSTATE and transforms
     * it into a unified {@link Doctrine\DBAL\Exception\DriverException} subclass.
     *
     * @param string                                $message   The DBAL exception message to use.
     * @param \Doctrine\DBAL\Driver\DriverException $exception The DBAL driver exception to convert.
     *
     * @return \Doctrine\DBAL\Exception\DriverException An instance of one of the DriverException subclasses.
     */
    public function convertException($message, DriverException $exception);
}

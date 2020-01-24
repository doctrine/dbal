<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Driver;

/**
 * Abstract Firebird/Interbase driver.
 *
 * <b>This Driver/Platform is in Beta state</b>
 * 
 * @author Andreas Prucha, Helicon Software Development <prucha@helicon.co.at>
 */
abstract class AbstractFbIbDriver implements \Doctrine\DBAL\Driver, ExceptionConverterDriver
{

    /**
     * Driver options
     * 
     * @var type 
     */
    protected $driverOptions = array();

    /**
     * Driver option for additional platform configuration
     * @see Abstract
     */
    const DRIVER_OPTION_PLATFORM_OPTIOS = 'FbIbPlatformOptions';
    
    /**
     * {@inheritDoc}
     * 
     * This function does not actually 
     * 
     * @param array $params
     * @param type $username
     * @param type $password
     * @param array $driverOptions
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        $this->setDriverOptions($driverOptions);
    }        

    /**
     * {@inheritDoc}
     * 
     * @param type $message
     * @param \Doctrine\DBAL\Driver\DriverException $exception
     */
    public function convertException($message, DriverException $exception)
    {
        $message = 'Error ' . $exception->getErrorCode() . ': ' . $message;
        switch ($exception->getErrorCode()) {
            case -104: {
                    return new \Doctrine\DBAL\Exception\SyntaxErrorException($message, $exception);
                }
            case -204: {
                    if (preg_match('/.*(dynamic sql error).*(table unknown).*/i', $message)) {
                        return new \Doctrine\DBAL\Exception\TableNotFoundException($message, $exception);
                    }
                    if (preg_match('/.*(dynamic sql error).*(ambiguous field name).*/i', $message)) {
                        return new \Doctrine\DBAL\Exception\NonUniqueFieldNameException($message, $exception);
                    }
                    break;
                }
            case -206: {
                    if (preg_match('/.*(dynamic sql error).*(table unknown).*/i', $message)) {
                        return new \Doctrine\DBAL\Exception\InvalidFieldNameException($message, $exception);
                    }
                    if (preg_match('/.*(dynamic sql error).*(column unknown).*/i', $message)) {
                        return new \Doctrine\DBAL\Exception\InvalidFieldNameException($message, $exception);
                    }
                    break;
                }
            case -803: {
                    return new \Doctrine\DBAL\Exception\UniqueConstraintViolationException($message, $exception);
                }
            case -530: {
                    return new \Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException($message, $exception);
                }
            case -607: {
                    if (preg_match('/.*(unsuccessful metadata update Table).*(already exists).*/i', $message)) {
                        return new \Doctrine\DBAL\Exception\TableExistsException($message, $exception);
                    }
                    break;
                }
            case -902: {
                    return new \Doctrine\DBAL\Exception\ConnectionException($message, $exception);
                }
        }

        return new \Doctrine\DBAL\Exception\DriverException($message, $exception);

    }

    /**
     * Sets driver options
     * 
     * @param array $optionsToSet
     */
    public function setDriverOptions(array $optionsToSet)
    {
        $this->driverOptions = array_merge_recursive($this->driverOptions, $optionsToSet);
    }

    /**
     * Returns the driver options
     */
    public function getDriverOptions()
    {
        return $this->driverOptions;
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabasePlatform()
    {
        $result = new \Doctrine\DBAL\Platforms\FirebirdPlatform();
        if (is_array($this->driverOptions) && isset($this->driverOptions[self::DRIVER_OPTION_PLATFORM_OPTIOS])) {
            $result->setPlatformOptions($this->driverOptions[self::DRIVER_OPTION_PLATFORM_OPTIOS]);
        }
        return $result;
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\FirebirdSchemaManager($conn);
    }

    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }

}

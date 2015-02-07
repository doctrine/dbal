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

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\InformixPlatform;
use Doctrine\DBAL\Schema\InformixSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface
 * for IBM Informix based drivers.
 *
 */
abstract class AbstractInformixDriver implements Driver, ExceptionConverterDriver, VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();

        return $params['dbname'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new InformixPlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new InformixSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function convertException($message, DriverException $exception)
    {

        switch ( $exception->getErrorCode() ) {
            case '-239':
            case '-268':
                return new Exception\UniqueConstraintViolationException($message, $exception);

            case '-206':
                return new Exception\TableNotFoundException($message, $exception);

            case '-310':
                return new Exception\TableExistsException($message, $exception);

            case '-691':
            case '-692':
            case '-26018':
                return new Exception\ForeignKeyConstraintViolationException($message, $exception);

            case '-391':
                return new Exception\NotNullConstraintViolationException($message, $exception);

            case '-217':
                return new Exception\InvalidFieldNameException($message, $exception);

            case '-324':
                return new Exception\NonUniqueFieldNameException($message, $exception);

            case '-201':
                return new Exception\SyntaxErrorException($message, $exception);

            case '-908':
            case '-930':
            case '-951':
                return new Exception\ConnectionException($message, $exception);

        }

        // In some cases the exception doesn't have the driver-specific error code

        if ( self::isErrorAccessDeniedMessage($exception->getMessage()) ) {
            return new Exception\ConnectionException($message, $exception);
        }

        return new Exception\DriverException($message, $exception);
    }

    /**
     * Checks if a message means an "access denied error".
     *
     * @param string
     * @return boolean
     */
    protected static function isErrorAccessDeniedMessage($message)
    {
        if ( strpos($message, 'Incorrect password or user') !== false ||
            strpos($message, 'Cannot connect to database server') !== false ||
            preg_match('/Attempt to connect to database server (.*) failed/', $message) ) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see http://www-01.ibm.com/support/knowledgecenter/SSGU8G_11.50.0/com.ibm.sqls.doc/ids_sqs_1491.htm
     */
    public function createDatabasePlatformForVersion($version)
    {
        $regex = '/^(?P<server_type>.*)
            (?i:\s+Version\s+)
            (?P<major>\d+)\.
            (?P<minor>\d+)\.
            (?P<so>F|H|T|U)
            (?P<level>[[:alnum:]]+)/x';

        if ( ! preg_match($regex, $version, $versionParts) ) {
            throw DBALException::invalidPlatformVersionSpecified(
                $version,
                '<server_type> Version <major>.<minor><os><level>'
            );
        }

        // Right now only exists one platform for all versions
        return $this->getDatabasePlatform();
    }
}

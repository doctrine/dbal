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

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class Driver implements DriverInterface, ExceptionConverterDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        try {
            return new MysqliConnection($params, $username, $password, $driverOptions);
        } catch (MysqliException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'mysqli';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\MySqlSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new \Doctrine\DBAL\Platforms\MySqlPlatform();
    }

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
    public function convertExceptionCode(\Exception $exception)
    {
        if (strpos($exception->getMessage(), 'Table') === 0) {
            if (strpos($exception->getMessage(), 'doesn\'t exist') !== false) {
                return DBALException::ERROR_UNKNOWN_TABLE;
            }

            if (strpos($exception->getMessage(), 'already exists') !== false) {
                return DBALException::ERROR_TABLE_ALREADY_EXISTS;
            }
        }

        if (strpos($exception->getMessage(), 'Unknown column') === 0) {
            return DBALException::ERROR_BAD_FIELD_NAME;
        }

        if (strpos($exception->getMessage(), 'Cannot delete or update a parent row: a foreign key constraint fails') !== false) {
            return DBALException::ERROR_FOREIGN_KEY_CONSTRAINT;
        }

        if (strpos($exception->getMessage(), 'Duplicate entry') !== false) {
            return DBALException::ERROR_DUPLICATE_KEY;
        }

        if (strpos($exception->getMessage(), 'Column not found: 1054 Unknown column') !== false) {
            return DBALException::ERROR_BAD_FIELD_NAME;
        }

        if (strpos($exception->getMessage(), 'in field list is ambiguous') !== falsE) {
            return DBALException::ERROR_NON_UNIQUE_FIELD_NAME;
        }

        if (strpos($exception->getMessage(), 'You have an error in your SQL syntax; check the manual') !== false) {
            return DBALException::ERROR_SYNTAX;
        }

        if (strpos($exception->getMessage(), 'Access denied for user') !== false) {
            return DBALException::ERROR_ACCESS_DENIED;
        }

        if (strpos($exception->getMessage(), 'getaddrinfo failed: Name or service not known') !== false) {
            return DBALException::ERROR_ACCESS_DENIED;
        }

        if (strpos($exception->getMessage(), ' cannot be null')) {
            return DBALException::ERROR_NOT_NULL;
        }

        return 0;
    }
}

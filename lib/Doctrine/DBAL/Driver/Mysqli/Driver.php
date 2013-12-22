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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Platforms\MySqlPlatform;

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
    public function getSchemaManager(Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\MySqlSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new MySqlPlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(Connection $conn)
    {
        $params = $conn->getParams();

        return $params['dbname'];
    }

    /**
     * {@inheritdoc}
     *
     * @link http://dev.mysql.com/doc/refman/5.7/en/error-messages-client.html
     * @link http://dev.mysql.com/doc/refman/5.7/en/error-messages-server.html
     */
    public function convertExceptionCode(\Exception $exception)
    {
        switch ($exception->getCode()) {
            case '1050':
                return DBALException::ERROR_TABLE_ALREADY_EXISTS;

            case '1051':
            case '1146':
                return DBALException::ERROR_UNKNOWN_TABLE;

            case '1216':
            case '1217':
            case '1451':
            case '1452':
                return DBALException::ERROR_FOREIGN_KEY_CONSTRAINT;

            case '1062':
            case '1557':
            case '1569':
            case '1586':
                return DBALException::ERROR_DUPLICATE_KEY;

            case '1054':
            case '1166':
            case '1611':
                return DBALException::ERROR_BAD_FIELD_NAME;

            case '1052':
            case '1060':
            case '1110':
                return DBALException::ERROR_NON_UNIQUE_FIELD_NAME;

            case '1064':
            case '1149':
            case '1287':
            case '1341':
            case '1342':
            case '1343':
            case '1344':
            case '1382':
            case '1479':
            case '1541':
            case '1554':
            case '1626':
                return DBALException::ERROR_SYNTAX;

            case '1044':
            case '1045':
            case '1046':
            case '1049':
            case '1095':
            case '1142':
            case '1143':
            case '1227':
            case '1370':
            case '2002':
            case '2005':
                return DBALException::ERROR_ACCESS_DENIED;

            case '1048':
            case '1121':
            case '1138':
            case '1171':
            case '1252':
            case '1263':
            case '1566':
                return DBALException::ERROR_NOT_NULL;
        }

        return 0;
    }
}

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
use Doctrine\DBAL\Platforms\ASEPlatform;
use Doctrine\DBAL\Platforms\ASE150Platform;
use Doctrine\DBAL\Platforms\ASE155Platform;
use Doctrine\DBAL\Platforms\ASE157Platform;
use Doctrine\DBAL\Platforms\ASE160Platform;
use Doctrine\DBAL\Schema\ASESchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Exception;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for ASE based drivers.
 *
 * @author Maximilian Ruta <mr@xtain.net>
 * @link   www.doctrine-project.org
 * @since  2.6
 */
abstract class AbstractASEDriver implements Driver, ExceptionConverterDriver, VersionAwarePlatformDriver
{
    /**
     * @var array
     */
    protected $platformOptions = array();

    protected function initializeConnection(Connection $connection)
    {
        // you have to enable quoted_identifier in sybase to use them
        $connection->exec("SET quoted_identifier ON");

        if (isset($this->platformOptions['textsize'])) {
            $connection->exec("SET textsize " . $this->platformOptions['textsize']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabasePlatformForVersion($version)
    {
        $version = preg_replace('/^Adaptive Server Enterprise\//', '', $version);

        $matches = [];
        if (!preg_match('/([0-9]+)\.?([0-9]+)?.?([0-9]+)?.?([0-9]+)?(\/.*)?/i', $version, $matches)) {
            throw DBALException::invalidPlatformVersionSpecified(
                $version,
                '<product>/<major_version>.<minor_version>.<patch_version>.<build_version>'
            );
        }

        $versionParts = [];
        for ($i = 0; $i <= 4; $i++) {
            if (!isset($matches[$i])) {
                $matches[$i] = "0";
            }
            $versionParts[$i] = $matches[$i];
        }

        array_shift($versionParts);

        $version = "";
        foreach ($versionParts as $versionPart) {
            $version .= $versionPart . ".";
        }

        $version = rtrim($version, ".");

        switch(true) {
            case version_compare($version, '16.0.0.0', '>='):
                return new ASE160Platform($this->platformOptions);
            case version_compare($version, '15.7.0.0', '>='):
                return new ASE157Platform($this->platformOptions);
            case version_compare($version, '15.5.0.0', '>='):
                return new ASE155Platform($this->platformOptions);
            case version_compare($version, '15.0.0.0', '>='):
                return new ASE150Platform($this->platformOptions);
            default:
                return new ASEPlatform($this->platformOptions);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();

        if (isset($params['dbname'])) {
            return $params['dbname'];
        }

        return $conn->query('SELECT DB_NAME()')->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new ASE150Platform($this->platformOptions);
    }

    /**
     * {@inheritdoc}
     */

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new ASESchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function convertException($message, DriverException $exception)
    {
        switch (true) {
            case stripos($exception->getMessage(), 'Attempt to insert duplicate key row in object') === 0:
                return new Exception\UniqueConstraintViolationException($message, $exception);
            case preg_match('/^.*not found. Specify owner\.objectname/', $exception->getMessage()):
                return new Exception\TableNotFoundException($message, $exception);
            case preg_match('/There is already an object named /', $exception->getMessage()):
                return new Exception\TableExistsException($message, $exception);
            case preg_match('/Foreign key constraint violation occurred/i', $exception->getMessage()):
            case preg_match('/Dependent foreign key constraint violation in a referential integrity constraint/i', $exception->getMessage()):
            case preg_match('/there are referential constraints defined/i', $exception->getMessage()):
                return new Exception\ForeignKeyConstraintViolationException($message, $exception);
            case preg_match('/The column value in table .* does not allow null values/i', $exception->getMessage()):
                return new Exception\NotNullConstraintViolationException($message, $exception);
            case preg_match('/Invalid column name/i', $exception->getMessage()):
                return new Exception\InvalidFieldNameException($message, $exception);
            case preg_match('/Ambiguous column name/i', $exception->getMessage()):
                return new Exception\NonUniqueFieldNameException($message, $exception);
            case preg_match('/Incorrect syntax near/i', $exception->getMessage()):
                return new Exception\SyntaxErrorException($message, $exception);
            case preg_match('/Sybase:[\s]+Unable to connect/i', $exception->getMessage()):
                return new Exception\ConnectionException($message, $exception);
        }

        return new Exception\DriverException($message, $exception);
    }
}

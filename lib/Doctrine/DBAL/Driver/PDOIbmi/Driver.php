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
 * 
 * This driver requieres a odbc dsn.
 * The name of the dsn "[BPRUEBAS]" should be the same of the database 
 * "DefaultLibraries        = BDPRUEBAS", it sould look like this:
 * 
 * [BDPRUEBAS]
 * Description        = iSeries Access ODBC Driver
 * Driver        = iSeries Access ODBC Driver
 * System        = 10.25.2.7
 * UserID        =
 * Password        =
 * Naming        = 0
 * DefaultLibraries        = BDPRUEBAS
 * Database        =
 * ConnectionType        = 0
 * CommitMode        = 2
 * ExtendedDynamic        = 1
 * DefaultPkgLibrary        = QGPL
 * DefaultPackage        = A/DEFAULT(IBM),2,0,1,0,512
 * AllowDataCompression        = 1
 * MaxFieldLength        = 32
 * BlockFetch        = 1
 * BlockSizeKB        = 128
 * ExtendedColInfo        = 0
 * LibraryView        = 0
 * AllowUnsupportedChar        = 0
 * ForceTranslation        = 0
 * Trace        = 0 
 * 
 */

namespace Doctrine\DBAL\Driver\PDOIbmi;

use Doctrine\DBAL\Connection;

/**
 * Driver for the PDO IBM extension
 *
 * @link        www.doctrine-project.com
 * @since       2.5
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author      Jonathan Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 */
class Driver implements \Doctrine\DBAL\Driver {

    /**
     * Attempts to establish a connection with the underlying driver.
     *
     * @param array $params
     * @param string $username
     * @param string $password
     * @param array $driverOptions
     * @return \Doctrine\DBAL\Driver\Connection
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        $conn = new PDOIbmiConnection(
                $this->_constructPdoDsn($params), $username, $password, $driverOptions
        );

        return $conn;
    }

    /**
     * Constructs the ODBC PDO DSN.
     * To make things simple the odbc dsn created in the machine
     * should be created with the same name of the database
     *
     * @return string  The DSN.
     */
    private function _constructPdoDsn(array $params)
    {
        return 'odbc:' . $params['dbname'];
    }

    /**
     * Gets the DatabasePlatform instance that provides all the metadata about
     * the platform this driver connects to.
     *
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform The database platform.
     */
    public function getDatabasePlatform()
    {
        return new \Doctrine\DBAL\Platforms\DB2iSeriesPlatform;
    }

    /**
     * Gets the SchemaManager that can be used to inspect and change the underlying
     * database schema of the platform this driver connects to.
     *
     * @param  \Doctrine\DBAL\Connection $conn
     * @return \Doctrine\DBAL\Schema\DB2SchemaManager
     */
    public function getSchemaManager(Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\DB2iSeriesSchemaManager($conn);
    }

    /**
     * Gets the name of the driver.
     *
     * Things are different in DB2 flavor, this implementation works for
     * DB2 iSeries and probably for z/OS
     *
     * @return string The name of the driver.
     */
    public function getName()
    {
        return 'pdo_ibm_i';
    }

    /**
     * Get the name of the database connected to for this driver.
     *
     * @param  \Doctrine\DBAL\Connection $conn
     * @return string $database
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }

}
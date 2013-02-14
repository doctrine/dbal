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

namespace Doctrine\DBAL\Driver\SQLSrv;

/**
 * Driver for ext/sqlsrv
 */
class Driver implements \Doctrine\DBAL\Driver
{
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        if (!isset($params['host'])) {
            throw new SQLSrvException("Missing 'host' in configuration for sqlsrv driver.");
        }
        if (!isset($params['dbname'])) {
            throw new SQLSrvException("Missing 'dbname' in configuration for sqlsrv driver.");
        }

        $serverName = $params['host'];
        if (isset($params['port'])) {
            $serverName .= ', ' . $params['port'];
        }
        $driverOptions['Database'] = $params['dbname'];
        $driverOptions['UID'] = $username;
        $driverOptions['PWD'] = $password;

        if (!isset($driverOptions['ReturnDatesAsStrings'])) {
            $driverOptions['ReturnDatesAsStrings'] = 1;
        }

        return new SQLSrvConnection($serverName, $driverOptions);
    }

    public function getDatabasePlatform()
    {
        return new \Doctrine\DBAL\Platforms\SQLServer2008Platform();
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\SQLServerSchemaManager($conn);
    }

    public function getName()
    {
        return 'sqlsrv';
    }

    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }
}


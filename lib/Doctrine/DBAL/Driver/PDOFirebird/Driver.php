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

namespace Doctrine\DBAL\Driver\PDOFirebird;

/**
 * PDO Firebird driver.
 * 
 * <b>This driver is EXPERIMENTAL. It's strongly recommended to use ibase_firebird instead.</b>
 *
 * The Firebird PDO driver currently suffers from the following limitations and bugs:
 *
 * <b>Savepoints</b>: Firebird supports savepoints. These are used to simulate nested transactions in Doctrine. 
 * The Firebird PDO driver raises an exception if savepoints are used.
 * 
 * <b>BLOBs</b>: The Firebird PDO driver runs into memory leaks quite quickly if BLOBs are used.
 * 
 * <b>Transaction isolation</b>: There is no way to configure the transaction isolation level.
 * 
 * In order to workaround the limitations, this driver calls FirebirdPlatform->setInPdoContext(true) to configure the platform to disable savepoints and use varchars instead of blobs. * 
 * @author Andreas Prucha, Helicon Software Development <prucha@helicon.co.at>
 * @experimental
 */
class Driver extends \Doctrine\DBAL\Driver\AbstractFbIbDriver
{

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
            return new \Doctrine\DBAL\Driver\PDOFirebird\PDOConnection(
                    $this->constructPdoDsn($params), $username, $password, $driverOptions
            );
    }

    /**
     * Constructs the Firebird PDO DSN.
     *
     * @return string  The DSN.
     */
    protected function constructPdoDsn(array $params)
    {
        $dsn = 'firebird:dbname=';
        if (isset($params['host'])) {
            $dsn .= $params['host'] . ':';
        }
//        $dsn .= '10.10.21.127:';
        if (isset($params['dbname'])) {
            $dsn .= $params['dbname'] . ';';
        }
        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }
        if (isset($params['role'])) {
            $dsn .= 'role=' . $params['role'] . ';';
        }
        return $dsn;
    }

    public function getName()
    {
        return 'pdo_firebird';
    }

    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }
    
    public function getDatabasePlatform()
    {
        $result = parent::getDatabasePlatform();
        $result->setInPdoContext(true);
        return $result;
    }

}

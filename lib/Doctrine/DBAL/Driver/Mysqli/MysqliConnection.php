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

use Doctrine\DBAL\Driver\Connection as Connection;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class MysqliConnection implements Connection
{
    /**
     * @var \mysqli
     */
    private $_conn;

    /**
     * @param array  $params
     * @param string $username
     * @param string $password
     * @param array  $driverOptions
     *
     * @throws \Doctrine\DBAL\Driver\Mysqli\MysqliException
     */
    public function __construct(array $params, $username, $password, array $driverOptions = array())
    {
        $port = isset($params['port']) ? $params['port'] : ini_get('mysqli.default_port');
        $socket = isset($params['unix_socket']) ? $params['unix_socket'] : ini_get('mysqli.default_socket');
        $dbname = isset($params['dbname']) ? $params['dbname'] : null;

        $this->_conn = mysqli_init();
        if ( ! $this->_conn->real_connect($params['host'], $username, $password, $dbname, $port, $socket)) {
            throw new MysqliException($this->_conn->connect_error, $this->_conn->connect_errno);
        }

        if (isset($params['charset'])) {
            $this->_conn->set_charset($params['charset']);
        }
    }

    /**
     * Retrieves mysqli native resource handle.
     *
     * Could be used if part of your application is not using DBAL.
     *
     * @return \mysqli
     */
    public function getWrappedResourceHandle()
    {
        return $this->_conn;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString)
    {
        return new MysqliStatement($this->_conn, $prepareString);
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type=\PDO::PARAM_STR)
    {
        return "'". $this->_conn->escape_string($input) ."'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        $this->_conn->query($statement);
        return $this->_conn->affected_rows;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        return $this->_conn->insert_id;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->_conn->query('START TRANSACTION');
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->_conn->commit();
    }

    /**
     * {@inheritdoc}non-PHPdoc)
     */
    public function rollBack()
    {
        return $this->_conn->rollback();
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->_conn->errno;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->_conn->error;
    }
}

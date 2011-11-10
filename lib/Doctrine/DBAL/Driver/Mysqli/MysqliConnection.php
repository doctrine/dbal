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
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class MysqliConnection implements ConnectionInterface
{
    /**
     * @var \mysqli
     */
    private $_conn;

    public function __construct(array $params, $username, $password, array $driverOptions = array())
    {
        $port = isset($params['port']) ? $params['port'] : ini_get('mysqli.default_port');
        $socket = isset($params['unix_socket']) ? $params['unix_socket'] : ini_get('mysqli.default_socket');

        $this->_conn = new \mysqli($params['host'], $username, $password, $params['dbname'], $port, $socket);

        if (isset($params['charset'])) {
            $this->_conn->set_charset($params['charset']);
        }
    }

    public function prepare($prepareString)
    {
        return new MysqliStatement($this->_conn, $prepareString);
    }

    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function quote($input, $type=\PDO::PARAM_STR)
    {
        return "'". $this->_conn->escape_string($input) ."'";
    }

    public function exec($statement)
    {
        $this->_conn->query($statement);
        return $this->_conn->affected_rows;
    }

    public function lastInsertId($name = null)
    {
        return $this->_conn->insert_id;
    }

    public function beginTransaction()
    {
        $this->_conn->query('START TRANSACTION');
        return true;
    }

    public function commit()
    {
        return $this->_conn->commit();
    }

    public function rollBack()
    {
        return $this->_conn->rollback();
    }

    public function errorCode()
    {
        return $this->_conn->errno;
    }

    public function errorInfo()
    {
        return $this->_conn->error;
    }
}

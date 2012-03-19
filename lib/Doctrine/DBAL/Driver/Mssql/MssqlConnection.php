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

namespace Doctrine\DBAL\Driver\Mssql;

use Doctrine\DBAL\Driver\Connection as Connection;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class MssqlConnection implements Connection
{
    /**
     * @var \mysqli
     */
    private $_conn;

    public function __construct(array $params, $username, $password, array $driverOptions = array())
    {
        ini_set('mssql.datetimeconvert', 'Off');
        if (!empty($params['charset'])) {
            ini_set('mssql.charset', $params['charset']);
        }

        $port = isset($params['port']) ? $params['port'] : ini_get('mssql.default_port');
        //$socket = isset($params['unix_socket']) ? $params['unix_socket'] : ini_get('mysqli.default_socket');
        $this->_conn = mssql_connect($params['host'], $username, $password);
        mssql_select_db($params['dbname'], $this->_conn);

        if (!$this->_conn) {
            throw new MssqlException('Falha na conexáo');
        }

        /* if (isset($params['charset'])) {
            $this->_conn->set_charset($params['charset']);
        } */
    }

    /**
     * Retrieve mysqli native resource handle.
     *
     * Could be used if part of your application is not using DBAL
     *
     * @return mysqli
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
        return new MssqlStatement($this->_conn, $prepareString);
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
        return "'". addslashes($input) ."'";
        //return "'". $this->_conn->escape_string($input) ."'";
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
        $result = mssql_query('select SCOPE_IDENTITY() AS last_insert_id');
        if (! $result) {
            throw new MssqlException('Get lastInsertId');
        }

        $id = mssql_fetch_array($result, MSSQL_NUM);
        if (! $id) {
            throw new MssqlException('Get lastInsertId');
        }
        mssql_free_result($result);

        return $id[0];
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        return mssql_query('BEGIN TRANSACTION', $this->_conn);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return mssql_query('COMMIT TRANSACTION', $this->_conn);
    }

    /**
     * {@inheritdoc}non-PHPdoc)
     */
    public function rollBack()
    {

        return mssql_query('ROLLBACK TRANSACTION', $this->_conn);
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

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

use PDO;
use IteratorAggregate;
use Doctrine\DBAL\Driver\Statement;

/**
 * SQL Server Statement
 *
 * @since 2.3
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class SQLSrvStatement implements IteratorAggregate, Statement
{
    /**
     * SQLSRV Resource
     *
     * @var resource
     */
    private $conn;

    /**
     * SQL Statement to execute
     *
     * @var string
     */
    private $sql;

    /**
     * SQLSRV Statement Resource
     *
     * @var resource
     */
    private $stmt;

    /**
     * Parameters to bind
     *
     * @var array
     */
    private $params = array();

    /**
     * Translations
     *
     * @var array
     */
    private static $fetchMap = array(
        PDO::FETCH_BOTH => SQLSRV_FETCH_BOTH,
        PDO::FETCH_ASSOC => SQLSRV_FETCH_ASSOC,
        PDO::FETCH_NUM => SQLSRV_FETCH_NUMERIC,
    );

    /**
     * Fetch Style
     *
     * @param int
     */
    private $defaultFetchMode = PDO::FETCH_BOTH;

    /**
     * @var int|null
     */
    private $lastInsertId;

    /**
     * Append to any INSERT query to retrieve the last insert id.
     *
     * @var string
     */
    const LAST_INSERT_ID_SQL = ';SELECT SCOPE_IDENTITY() AS LastInsertId;';

    public function __construct($conn, $sql, $lastInsertId = null)
    {
        $this->conn = $conn;
        $this->sql = $sql;

        if (stripos($sql, 'INSERT INTO ') === 0) {
            $this->sql .= self::LAST_INSERT_ID_SQL;
            $this->lastInsertId = $lastInsertId;
        }
    }

    public function bindValue($param, $value, $type = null)
    {
        return $this->bindParam($param, $value, $type,null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null)
    {
        if (!is_numeric($column)) {
            throw new SQLSrvException("sqlsrv does not support named parameters to queries, use question mark (?) placeholders instead.");
        }

        if ($type === \PDO::PARAM_LOB) {
            $this->params[$column-1] = array($variable, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY), SQLSRV_SQLTYPE_VARBINARY('max'));
        } else {
            $this->params[$column-1] = $variable;
        }
    }

    public function closeCursor()
    {
        if ($this->stmt) {
            sqlsrv_free_stmt($this->stmt);
        }
    }

    public function columnCount()
    {
        return sqlsrv_num_fields($this->stmt);
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        if ($errors) {
            return $errors[0]['code'];
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return sqlsrv_errors(SQLSRV_ERR_ERRORS);
    }

    public function execute($params = null)
    {
        if ($params) {
            $hasZeroIndex = array_key_exists(0, $params);
            foreach ($params as $key => $val) {
                $key = ($hasZeroIndex && is_numeric($key)) ? $key + 1 : $key;
                $this->bindValue($key, $val);
            }
        }

        $this->stmt = sqlsrv_query($this->conn, $this->sql, $this->params);
        if ( ! $this->stmt) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        if ($this->lastInsertId) {
            sqlsrv_next_result($this->stmt);
            sqlsrv_fetch($this->stmt);
            $this->lastInsertId->setId( sqlsrv_get_field($this->stmt, 0) );
        }
    }

    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->defaultFetchMode = $fetchMode;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();
        return new \ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;
        if (isset(self::$fetchMap[$fetchMode])) {
            return sqlsrv_fetch_array($this->stmt, self::$fetchMap[$fetchMode]);
        } else if ($fetchMode == PDO::FETCH_OBJ || $fetchMode == PDO::FETCH_CLASS) {
            $className = null;
            $ctorArgs = null;
            if (func_num_args() >= 2) {
                $args = func_get_args();
                $className = $args[1];
                $ctorArgs = (isset($args[2])) ? $args[2] : array();
            }
            return sqlsrv_fetch_object($this->stmt, $className, $ctorArgs);
        }

        throw new SQLSrvException("Fetch mode is not supported!");
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $className = null;
        $ctorArgs = null;
        if (func_num_args() >= 2) {
            $args = func_get_args();
            $className = $args[1];
            $ctorArgs = (isset($args[2])) ? $args[2] : array();
        }

        $rows = array();
        while ($row = $this->fetch($fetchMode, $className, $ctorArgs)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(PDO::FETCH_NUM);
        return $row[$columnIndex];
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return sqlsrv_rows_affected($this->stmt);
    }
}


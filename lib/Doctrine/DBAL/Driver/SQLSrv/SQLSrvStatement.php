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

use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use Doctrine\DBAL\Driver\Statement;
use const SQLSRV_ENC_BINARY;
use const SQLSRV_ERR_ERRORS;
use const SQLSRV_FETCH_ASSOC;
use const SQLSRV_FETCH_BOTH;
use const SQLSRV_FETCH_NUMERIC;
use const SQLSRV_PARAM_IN;
use function array_key_exists;
use function count;
use function func_get_args;
use function in_array;
use function is_numeric;
use function sqlsrv_errors;
use function sqlsrv_execute;
use function sqlsrv_fetch;
use function sqlsrv_fetch_array;
use function sqlsrv_fetch_object;
use function sqlsrv_get_field;
use function sqlsrv_next_result;
use function sqlsrv_num_fields;
use function SQLSRV_PHPTYPE_STREAM;
use function SQLSRV_PHPTYPE_STRING;
use function sqlsrv_prepare;
use function sqlsrv_rows_affected;
use function SQLSRV_SQLTYPE_VARBINARY;
use function stripos;

/**
 * SQL Server Statement.
 *
 * @since 2.3
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class SQLSrvStatement implements IteratorAggregate, Statement
{
    /**
     * The SQLSRV Resource.
     *
     * @var resource
     */
    private $conn;

    /**
     * The SQL statement to execute.
     *
     * @var string
     */
    private $sql;

    /**
     * The SQLSRV statement resource.
     *
     * @var resource|null
     */
    private $stmt;

    /**
     * References to the variables bound as statement parameters.
     *
     * @var array
     */
    private $variables = [];

    /**
     * Bound parameter types.
     *
     * @var array
     */
    private $types = [];

    /**
     * Translations.
     *
     * @var array
     */
    private static $fetchMap = [
        FetchMode::MIXED       => SQLSRV_FETCH_BOTH,
        FetchMode::ASSOCIATIVE => SQLSRV_FETCH_ASSOC,
        FetchMode::NUMERIC     => SQLSRV_FETCH_NUMERIC,
    ];

    /**
     * The name of the default class to instantiate when fetching class instances.
     *
     * @var string
     */
    private $defaultFetchClass = '\stdClass';

    /**
     * The constructor arguments for the default class to instantiate when fetching class instances.
     *
     * @var mixed[]
     */
    private $defaultFetchClassCtorArgs = [];

    /**
     * The fetch style.
     *
     * @var int
     */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * The last insert ID.
     *
     * @var \Doctrine\DBAL\Driver\SQLSrv\LastInsertId|null
     */
    private $lastInsertId;

    /**
     * Indicates whether the statement is in the state when fetching results is possible
     *
     * @var bool
     */
    private $result = false;

    /**
     * Append to any INSERT query to retrieve the last insert id.
     *
     * @var string
     */
    const LAST_INSERT_ID_SQL = ';SELECT SCOPE_IDENTITY() AS LastInsertId;';

    /**
     * @param resource                                       $conn
     * @param string                                         $sql
     * @param \Doctrine\DBAL\Driver\SQLSrv\LastInsertId|null $lastInsertId
     */
    public function __construct($conn, $sql, LastInsertId $lastInsertId = null)
    {
        $this->conn = $conn;
        $this->sql = $sql;

        if (stripos($sql, 'INSERT INTO ') === 0) {
            $this->sql .= self::LAST_INSERT_ID_SQL;
            $this->lastInsertId = $lastInsertId;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        if (!is_numeric($param)) {
            throw new SQLSrvException(
                'sqlsrv does not support named parameters to queries, use question mark (?) placeholders instead.'
            );
        }

        $this->variables[$param] = $value;
        $this->types[$param] = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        if (!is_numeric($column)) {
            throw new SQLSrvException("sqlsrv does not support named parameters to queries, use question mark (?) placeholders instead.");
        }

        $this->variables[$column] =& $variable;
        $this->types[$column] = $type;

        // unset the statement resource if it exists as the new one will need to be bound to the new variable
        $this->stmt = null;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        // not having the result means there's nothing to close
        if (!$this->result) {
            return true;
        }

        // emulate it by fetching and discarding rows, similarly to what PDO does in this case
        // @link http://php.net/manual/en/pdostatement.closecursor.php
        // @link https://github.com/php/php-src/blob/php-7.0.11/ext/pdo/pdo_stmt.c#L2075
        // deliberately do not consider multiple result sets, since doctrine/dbal doesn't support them
        while (sqlsrv_fetch($this->stmt));

        $this->result = false;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return sqlsrv_num_fields($this->stmt);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return sqlsrv_errors(SQLSRV_ERR_ERRORS);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($params) {
            $hasZeroIndex = array_key_exists(0, $params);
            foreach ($params as $key => $val) {
                $key = ($hasZeroIndex && is_numeric($key)) ? $key + 1 : $key;
                $this->bindValue($key, $val);
            }
        }

        if ( ! $this->stmt) {
            $this->stmt = $this->prepare();
        }

        if (!sqlsrv_execute($this->stmt)) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        if ($this->lastInsertId) {
            sqlsrv_next_result($this->stmt);
            sqlsrv_fetch($this->stmt);
            $this->lastInsertId->setId(sqlsrv_get_field($this->stmt, 0));
        }

        $this->result = true;
    }

    /**
     * Prepares SQL Server statement resource
     *
     * @return resource
     * @throws SQLSrvException
     */
    private function prepare()
    {
        $params = [];

        foreach ($this->variables as $column => &$variable) {
            switch ($this->types[$column]) {
                case ParameterType::LARGE_OBJECT:
                    $params[$column - 1] = [
                        &$variable,
                        SQLSRV_PARAM_IN,
                        SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY),
                        SQLSRV_SQLTYPE_VARBINARY('max'),
                    ];
                    break;

                case ParameterType::BINARY:
                    $params[$column - 1] = [
                        &$variable,
                        SQLSRV_PARAM_IN,
                        SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY),
                    ];
                    break;

                default:
                    $params[$column - 1] =& $variable;
                    break;
            }
        }

        $stmt = sqlsrv_prepare($this->conn, $this->sql, $params);

        if (!$stmt) {
            throw SQLSrvException::fromSqlSrvErrors();
        }

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->defaultFetchMode          = $fetchMode;
        $this->defaultFetchClass         = $arg2 ?: $this->defaultFetchClass;
        $this->defaultFetchClassCtorArgs = $arg3 ? (array) $arg3 : $this->defaultFetchClassCtorArgs;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLSrvException
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        // do not try fetching from the statement if it's not expected to contain result
        // in order to prevent exceptional situation
        if (!$this->result) {
            return false;
        }

        $args      = func_get_args();
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        if ($fetchMode === FetchMode::COLUMN) {
            return $this->fetchColumn();
        }

        if (isset(self::$fetchMap[$fetchMode])) {
            return sqlsrv_fetch_array($this->stmt, self::$fetchMap[$fetchMode]) ?: false;
        }

        if (in_array($fetchMode, [FetchMode::STANDARD_OBJECT, FetchMode::CUSTOM_OBJECT], true)) {
            $className = $this->defaultFetchClass;
            $ctorArgs  = $this->defaultFetchClassCtorArgs;

            if (count($args) >= 2) {
                $className = $args[1];
                $ctorArgs  = $args[2] ?? [];
            }

            return sqlsrv_fetch_object($this->stmt, $className, $ctorArgs) ?: false;
        }

        throw new SQLSrvException('Fetch mode is not supported!');
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        $rows = [];

        switch ($fetchMode) {
            case FetchMode::CUSTOM_OBJECT:
                while (($row = $this->fetch(...func_get_args())) !== false) {
                    $rows[] = $row;
                }
                break;

            case FetchMode::COLUMN:
                while (($row = $this->fetchColumn()) !== false) {
                    $rows[] = $row;
                }
                break;

            default:
                while (($row = $this->fetch($fetchMode)) !== false) {
                    $rows[] = $row;
                }
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        if (false === $row) {
            return false;
        }

        return $row[$columnIndex] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return sqlsrv_rows_affected($this->stmt);
    }
}

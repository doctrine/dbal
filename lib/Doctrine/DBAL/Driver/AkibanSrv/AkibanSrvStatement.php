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

namespace Doctrine\DBAL\Driver\AkibanSrv;

use PDO;
use IteratorAggregate;
use Doctrine\DBAL\Driver\Statement;

/**
 * Akiban Server Statement
 *
 * @since 2.3
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 */
class AkibanSrvStatement implements IteratorAggregate, Statement
{
    /**
     * Akiban Server handle.
     *
     * @var resource
     */
    private $_dbh;

    /**
     * SQL statement to execute
     *
     * @var string
     */
    private $_statement;

    /**
     * randomly generated name for this statement.
     */
    private $_statementName;

    /**
     * query results
     */
    private $_results;

    /**
     * Akiban Server connection object.
     *
     * @var resource
     */
    private $_conn;

    /**
     * An array of the parameters for this statement.
     */
    private $_parameters = array();

    /**
     * The fetch mode for this statement.
     */
    private $_defaultFetchMode = PDO::FETCH_BOTH;

    private static $fetchModeMap = 
      array(
        PDO::FETCH_BOTH => PGSQL_BOTH,
        PDO::FETCH_ASSOC => PGSQL_ASSOC,
        PDO::FETCH_NUM => PGSQL_NUM,
      );

    public function __construct($dbh, $statement, AkibanSrvConnection $conn)
    {
        $this->_statement = $this->convertPositionalToNumberedParameters($statement);
        $this->_dbh = $dbh;
        $this->_conn = $conn;
        // generate a random name for this statement
        $this->_statementName = "my_query";
        $this->_results = false;
    }

    /**
     * Convert positional (?) into numbered parameters ($<num>).
     *
     * The PostgreSQL client libraries do not support positional parameters, hence
     * this method converts all positional parameters into numbered parameters.
     */
    private function convertPositionalToNumberedParameters($statement)
    {
        $count = 1;
        $inLiteral = false;
        $stmtLen = strlen($statement);
        for ($i = 0; $i < $stmtLen; $i++) {
            if ($statement[$i] == '?' && ! $inLiteral) {
                $param = "$" . $count;
                $len = strlen($param);
                $statement = substr_replace($statement, $param, $i, 1);
                $i += $len - 1;
                $stmtLen = strlen($statement);
                ++$count;
            } else if ($statement[$i] == "'" || $statement[$i] == '"') {
                $inLiteral = ! $inLiteral;
            }
        }

        return $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        return $this->bindParam($param, $value, $type, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null)
    {
        $this->_parameters[] = $variable;
    }

    public function closeCursor()
    {
        if ($this->_results) {
            return pg_free_result($this->_results);
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        if ($this->_results) {
            return pg_num_fields($this->_results);
        }
        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        return pg_last_error($this->dbh);
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return pg_last_error($this->dbh);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if (empty($this->_parameters) && is_null($params)) {
            $this->_results = pg_query($this->_dbh, $this->_statement);
        } else if (empty($this->_parameters) && ! is_null($params)) {
            $this->_results = pg_query_params($this->_dbh, $this->_statement, $params);
        } else {
            $this->_results = pg_query_params($this->_dbh, $this->_statement, $this->_parameters);
        }
        return $this->_results;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->_defaultFetchMode = $fetchMode;
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
    public function fetch($fetchMode = null, $rowPos = 0)
    {
        $fetchMode = $fetchMode ? : $this->_defaultFetchMode;
        if ( ! isset(self::$fetchModeMap[$fetchMode])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchMode);
        }
        if ($this->_results) {
            return pg_fetch_array($this->_results, $rowPos, self::$fetchModeMap[$fetchMode]);
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $fetchMode = $fetchMode ? : $this->_defaultFetchMode;
        if ( ! isset(self::$fetchModeMap[$fetchMode])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchMode);
        }
        if ($this->_results) {
            $result = array();
            if (self::$fetchModeMap[$fetchMode] == PGSQL_BOTH) {
                for ($i = 0; $i < pg_num_rows($this->_results); $i++) {
                    $row = $this->fetch($fetchMode, $i);
                    $result[] = $row;
                }
                return $result;
            } else if (self::$fetchModeMap[$fetchMode] == PGSQL_NUM) {
                for ($i = 0; $i < pg_num_rows($this->_results); $i++) {
                    $row = $this->fetch($fetchMode, $i);
                    $result[] = $row;
                }
                return $result;
            } else {
                return pg_fetch_all($this->_results);
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        if ($this->_results) {
            $row = pg_fetch_array($this->_results, 0, PGSQL_NUM);
            return isset($row[$columnIndex]) ? $row[$columnIndex] : false;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        if ($this->_results) {
            return pg_affected_rows($this->_results);
        }
        return 0;
    }
}


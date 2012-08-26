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
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since  2.4
 */
class AkibanSrvStatement implements IteratorAggregate, Statement
{
    /**
     * Akiban Server connection handle.
     *
     * @var resource
     */
    private $connectionHandle;

    /**
     * SQL statement to execute
     *
     * @var string
     */
    private $statement;

    /**
     * query results
     */
    private $results;

    /**
     * An array of the parameters for this statement.
     */
    private $parameters = array();

    /**
     * The fetch mode for this statement.
     */
    private $defaultFetchMode = PDO::FETCH_BOTH;

    private $className;
    private $constructorArguments;

    private static $fetchModeMap = array(
        PDO::FETCH_BOTH   => PGSQL_BOTH,
        PDO::FETCH_ASSOC  => PGSQL_ASSOC,
        PDO::FETCH_NUM    => PGSQL_NUM,
        PDO::FETCH_COLUMN => 1,
        PDO::FETCH_OBJ    => 1,
        PDO::FETCH_CLASS  => 1,
    );

    public function __construct($connectionHandle, 
                                $statement)
    {
        $this->statement = $this->convertPositionalToNumberedParameters($statement);
        $this->connectionHandle = $connectionHandle;
        $this->results = false;
        $this->className = null;
        $this->constructorArguments = null;
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
            if ($statement[$i] === '?' && ! $inLiteral) {
                $param = "$" . $count;
                $len = strlen($param);
                $statement = substr_replace($statement, $param, $i, 1);
                $i += $len - 1;
                $stmtLen = strlen($statement);
                ++$count;
            } else if ($statement[$i] === "'" || $statement[$i] === '"') {
                $inLiteral = ! $inLiteral;
            }
        }

        return $statement;
    }

    private function fetchRows($fetchMode)
    {
        $result = array();
        for ($i = 0; $i < pg_num_rows($this->results); $i++) {
            $result[] = $this->fetch($fetchMode, $i);
        }
        return $result;
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
        $this->parameters[] = $variable;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        if ($this->results) {
            $ret = pg_free_result($this->results);
            $this->results = false;
            return $ret;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        if ($this->results) {
            return pg_num_fields($this->results);
        }
        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        return pg_last_error($this->connectionHandle);
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return pg_last_error($this->connectionHandle);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ( ! empty($this->parameters)) {
            return $this->executeParameterizedQuery($this->statement, $this->parameters);
        }
        if (null !== $params) {
            return $this->executeParameterizedQuery($this->statement, $params);
        }
        return $this->executeQuery($this->statement);
    }

    private function executeParameterizedQuery($statement, $parameters)
    {
        $this->results = pg_query_params($this->connectionHandle, $statement, $parameters);
        return $this->results;
    }

    private function executeQuery($statement)
    {
        $this->results = pg_query($this->connectionHandle, $statement);
        return $this->results;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->defaultFetchMode = $fetchMode;
        if (($fetchMode === PDO::FETCH_OBJ || 
            $fetchMode === PDO::FETCH_CLASS) &&
            func_num_args() >= 2) {
            $args = func_get_args();
            $this->className = $args[1];
            $this->constructorArguments = (isset($args[2])) ? $args[2] : array();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->fetchAll());
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $rowPos = null)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        if (! isset(self::$fetchModeMap[$fetchMode])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchMode);
        }

        if (($fetchMode === PDO::FETCH_OBJ || $fetchMode === PDO::FETCH_CLASS) &&
            $this->results &&
            $this->className) {
            if (empty($this->constructorArguments)) {
                return pg_fetch_object($this->results, $rowPos, $this->className);
            }
            return pg_fetch_object($this->results, $rowPos, $this->className, $this->constructorArguments);
        }

        return pg_fetch_array($this->results, $rowPos, self::$fetchModeMap[$fetchMode]);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        if (! isset(self::$fetchModeMap[$fetchMode])) {
            throw new \InvalidArgumentException("Invalid fetch mode: " . $fetchMode);
        }

        $result = array();

        switch ($fetchMode) {
            case PDO::FETCH_OBJ:
            case PDO::FETCH_CLASS:
                if (func_num_args() >= 2) {
                    $args = func_get_args();
                    $this->className = $args[1];
                    $this->constructorArguments = (isset($args[2])) ? $args[2] : array();
                }
                $result = $this->fetchRows($fetchMode);
                break;
            case PDO::FETCH_BOTH:
            case PDO::FETCH_NUM:
                $result = $this->fetchRows($fetchMode);
                break;
            case PDO::FETCH_COLUMN:
                for ($i = 0; $i < pg_num_rows($this->results); $i++) {
                    for ($col = 0; $col < $this->columnCount(); $col++) {
                        $result[] = $this->fetchColumn($col, $i);
                    }
                }
                break;
            default:
                $result = pg_fetch_all($this->results);
                break;
        }

        /*
         * The native PostgreSQL client returns false if no
         * rows are returned. Since the fetchAll interface
         * specifies an array must be returned, make sure
         * an empty array is returned if in fact the PostgreSQL
         * client returned false.
         */
        return ($result === false) ? array() : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0, $rowPos = null)
    {
        if ($this->results) {
            $row = pg_fetch_array($this->results, $rowPos, PGSQL_NUM);
            return isset($row[$columnIndex]) ? $row[$columnIndex] : false;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        if ($this->results) {
            return pg_affected_rows($this->results);
        }
        return 0;
    }
}


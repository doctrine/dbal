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

    private $_parameters = array();

    public function __construct($dbh, $statement, AkibanSrvConnection $conn)
    {
        $this->_statement = $this->convertPositionalToNumberedParameters($statement);
        $this->_dbh = $dbh;
        $this->_conn = $conn;
        // generate a random name for this statement
        $this->_statementName = "my_query";
        $this->_results = false;
        //pg_prepare($this->_dbh, $this->_statementName, $this->_statement);
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
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        // TODO
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
        if (is_null($params)) {
            $args = array();
        } 
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
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        return pg_fetch_all($this->_results);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        // TODO
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


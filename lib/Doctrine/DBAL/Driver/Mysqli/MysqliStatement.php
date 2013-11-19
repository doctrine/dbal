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

use Doctrine\DBAL\Driver\Statement;
use PDO;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class MysqliStatement implements \IteratorAggregate, Statement
{
    /**
     * @var array
     */
    protected static $_paramTypeMap = array(
        PDO::PARAM_STR => 's',
        PDO::PARAM_BOOL => 'i',
        PDO::PARAM_NULL => 's',
        PDO::PARAM_INT => 'i',
        PDO::PARAM_LOB => 's' // TODO Support LOB bigger then max package size.
    );

    /**
     * @var \mysqli
     */
    protected $conn;

    /**
     * @var \mysqli_stmt
     */
    protected $stmt;

    /**
     * @var null|boolean|array
     */
    protected $columnNames;

    /**
     * @var null|array
     */
    protected $rowBindedValues;

    /**
     * @var array
     */
    protected $bindedValues;

    /**
     * @var string
     */
    protected $types;

    /**
     * Contains ref values for bindValue().
     *
     * @var array
     */
    protected $values = array();

    /**
     * @var integer
     */
    protected $defaultFetchMode = PDO::FETCH_BOTH;

    /**
     * @param \mysqli $conn
     * @param string  $prepareString
     *
     * @throws \Doctrine\DBAL\Driver\Mysqli\MysqliException
     */
    public function __construct(\mysqli $conn, $prepareString)
    {
        $this->conn = $conn;
        $this->stmt = $conn->prepare($prepareString);
        if (false === $this->stmt) {
            throw new MysqliException($this->conn->error, $this->conn->errno);
        }

        $paramCount = $this->stmt->param_count;
        if (0 < $paramCount) {
            $this->types = str_repeat('s', $paramCount);
            $this->bindedValues = array_fill(1 , $paramCount, null);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null)
    {
        if (null === $type) {
            $type = 's';
        } else {
            if (isset(self::$_paramTypeMap[$type])) {
                $type = self::$_paramTypeMap[$type];
            } else {
                throw new MysqliException("Unknown type: '{$type}'");
            }
        }

        $this->bindedValues[$column] =& $variable;
        $this->types[$column - 1] = $type;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        if (null === $type) {
            $type = 's';
        } else {
            if (isset(self::$_paramTypeMap[$type])) {
                $type = self::$_paramTypeMap[$type];
            } else {
                throw new MysqliException("Unknown type: '{$type}'");
            }
        }

        $this->values[$param] = $value;
        $this->bindedValues[$param] =& $this->values[$param];
        $this->types[$param - 1] = $type;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if (null !== $this->bindedValues) {
            if (null !== $params) {
                if ( ! $this->_bindValues($params)) {
                    throw new MysqliException($this->stmt->error, $this->stmt->errno);
                }
            } else {
                if (!call_user_func_array(array($this->stmt, 'bind_param'), array($this->types) + $this->bindedValues)) {
                    throw new MysqliException($this->stmt->error, $this->stmt->errno);
                }
            }
        }

        if ( ! $this->stmt->execute()) {
            throw new MysqliException($this->stmt->error, $this->stmt->errno);
        }

        if (null === $this->columnNames) {
            $meta = $this->stmt->result_metadata();
            if (false !== $meta) {
                $columnNames = array();
                foreach ($meta->fetch_fields() as $col) {
                    $columnNames[] = $col->name;
                }
                $meta->free();

                $this->columnNames = $columnNames;
                $this->rowBindedValues = array_fill(0, count($columnNames), NULL);

                $refs = array();
                foreach ($this->rowBindedValues as $key => &$value) {
                    $refs[$key] =& $value;
                }

                if (!call_user_func_array(array($this->stmt, 'bind_result'), $refs)) {
                    throw new MysqliException($this->stmt->error, $this->stmt->errno);
                }
            } else {
                $this->columnNames = false;
            }
        }

        // We have a result.
        if (false !== $this->columnNames) {
            $this->stmt->store_result();
        }

        return true;
    }

    /**
     * Binds a array of values to bound parameters.
     *
     * @param array $values
     *
     * @return boolean
     */
    private function _bindValues($values)
    {
        $params = array();
        $types = str_repeat('s', count($values));
        $params[0] = $types;

        foreach ($values as &$v) {
            $params[] =& $v;
        }

        return call_user_func_array(array($this->stmt, 'bind_param'), $params);
    }

    /**
     * @return boolean|array
     */
    private function _fetch()
    {
        $ret = $this->stmt->fetch();

        if (true === $ret) {
            $values = array();
            foreach ($this->rowBindedValues as $v) {
                $values[] = $v;
            }
            return $values;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        $values = $this->_fetch();
        if (null === $values) {
            return null;
        }

        if (false === $values) {
            throw new MysqliException($this->stmt->error, $this->stmt->errno);
        }

        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        switch ($fetchMode) {
            case PDO::FETCH_NUM:
                return $values;

            case PDO::FETCH_ASSOC:
                return array_combine($this->columnNames, $values);

            case PDO::FETCH_BOTH:
                $ret = array_combine($this->columnNames, $values);
                $ret += $values;
                return $ret;

            default:
                throw new MysqliException("Unknown fetch type '{$fetchMode}'");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $rows = array();
        if (PDO::FETCH_COLUMN == $fetchMode) {
            while (($row = $this->fetchColumn()) !== false) {
                $rows[] = $row;
            }
        } else {
            while (($row = $this->fetch($fetchMode)) !== null) {
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
        $row = $this->fetch(PDO::FETCH_NUM);
        if (null === $row) {
            return false;
        }

        return $row[$columnIndex];
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->stmt->errno;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->stmt->error;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        $this->stmt->free_result();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        if (false === $this->columnNames) {
            return $this->stmt->affected_rows;
        }
        return $this->stmt->num_rows;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->stmt->field_count;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->defaultFetchMode = $fetchMode;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();

        return new \ArrayIterator($data);
    }
}

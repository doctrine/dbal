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

use Doctrine\DBAL\Driver\Statement;
use PDO;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class MysqliStatement implements \IteratorAggregate, Statement
{
    protected static $_paramTypeMap = array(
        PDO::PARAM_STR => 's',
        PDO::PARAM_BOOL => 'i',
        PDO::PARAM_NULL => 's',
        PDO::PARAM_INT => 'i',
        PDO::PARAM_LOB => 's' // TODO Support LOB bigger then max package size.
    );

    protected $_conn;
    protected $_stmt;

    /**
     * @var null|false|array
     */
    protected $_columnNames;

    /**
     * @var null|array
     */
    protected $_rowBindedValues;

    /**
     * @var array
     */
    protected $_bindedValues;

    /**
     * Contains ref values for bindValue()
     *
     * @var array
     */
    protected $_values = array();

    protected $_defaultFetchStyle = PDO::FETCH_BOTH;

    public function __construct(\mysqli $conn, $prepareString)
    {
        $this->_conn = $conn;
        $this->_stmt = $conn->prepare($prepareString);
        if (false === $this->_stmt) {
            throw new MysqliException($this->_conn->error, $this->_conn->errno);
        }

        $paramCount = $this->_stmt->param_count;
        if (0 < $paramCount) {
            // Index 0 is types
            // Need to init the string else php think we are trying to access it as a array.
            $bindedValues = array(0 => str_repeat('s', $paramCount));
            $null = null;
            for ($i = 1; $i < $paramCount; $i++) {
                $bindedValues[] =& $null;
            }
            $this->_bindedValues = $bindedValues;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null)
    {
        if (null === $type) {
            $type = 's';
        } else {
            if (isset(self::$_paramTypeMap[$type])) {
                $type = self::$_paramTypeMap[$type];
            } else {
                throw new MysqliException("Unkown type: '{$type}'");
            }
        }

        $this->_bindedValues[$column] =& $variable;
        $this->_bindedValues[0][$column - 1] = 's';
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

        $this->_values[$param] = $value;
        $this->_bindedValues[$param] =& $this->_values[$param];
        $this->_bindedValues[0][$param - 1] = 's';
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if (null !== $this->_bindedValues) {
            if (null !== $params) {
                if (!$this->_bindValues($params)) {
                    throw new MysqliException($this->_stmt->error, $this->_stmt->errno);
                }
            } else {
                if (!call_user_func_array(array($this->_stmt, 'bind_param'), $this->_bindedValues)) {
                    throw new MysqliException($this->_stmt->error, $this->_stmt->errno);
                }
            }
        }

        if (!$this->_stmt->execute()) {
            throw new MysqliException($this->_stmt->error, $this->_stmt->errno);
        }

        if (null === $this->_columnNames) {
            $meta = $this->_stmt->result_metadata();
            if (false !== $meta) {
                $columnNames = array();
                foreach ($meta->fetch_fields() as $col) {
                    $columnNames[] = $col->name;
                }
                $meta->free();

                $this->_columnNames = $columnNames;
                $this->_rowBindedValues = array_fill(0, count($columnNames), NULL);

                $refs = array();
                foreach ($this->_rowBindedValues as $key => &$value) {
                    $refs[$key] =& $value;
                }

                if (!call_user_func_array(array($this->_stmt, 'bind_result'), $refs)) {
                    throw new MysqliException($this->_stmt->error, $this->_stmt->errno);
                }
            } else {
                $this->_columnNames = false;
            }
        }

        // We have a result.
        if (false !== $this->_columnNames) {
            $this->_stmt->store_result();
        }
        return true;
    }

    /**
     * Bind a array of values to bound parameters
     * 
     * @param array $values
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
        return call_user_func_array(array($this->_stmt, 'bind_param'), $params);
    }

    /**
     * @return null|false|array
     */
    private function _fetch()
    {
        $ret = $this->_stmt->fetch();

        if (true === $ret) {
            $values = array();
            foreach ($this->_rowBindedValues as $v) {
                // Mysqli converts them to a scalar type it can fit in.
                $values[] = null === $v ? null : (string)$v;
            }
            return $values;
        }
        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchStyle = null)
    {
        $values = $this->_fetch();
        if (null === $values) {
            return null;
        }

        if (false === $values) {
            throw new MysqliException($this->_stmt->error, $this->_stmt->errno);
        }

        $fetchStyle = $fetchStyle ?: $this->_defaultFetchStyle;

        switch ($fetchStyle) {
            case PDO::FETCH_NUM:
                return $values;

            case PDO::FETCH_ASSOC:
                return array_combine($this->_columnNames, $values);

            case PDO::FETCH_BOTH:
                $ret = array_combine($this->_columnNames, $values);
                $ret += $values;
                return $ret;

            default:
                throw new MysqliException("Unknown fetch type '{$fetchStyle}'");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchStyle = null)
    {
        $fetchStyle = $fetchStyle ?: $this->_defaultFetchStyle;

        $a = array();
        while (($row = $this->fetch($fetchStyle)) !== null) {
            $a[] = $row;
        }
        return $a;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(PDO::FETCH_NUM);
        if (null === $row) {
            return null;
        }
        return $row[$columnIndex];
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->_stmt->errno;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->_stmt->error;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        $this->_stmt->free_result();
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        if (false === $this->_columnNames) {
            return $this->_stmt->affected_rows;
        }
        return $this->_stmt->num_rows;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->_stmt->field_count;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode = PDO::FETCH_BOTH)
    {
        $this->_defaultFetchStyle = $fetchMode;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll($this->_defaultFetchStyle);
        return new \ArrayIterator($data);
    }
}

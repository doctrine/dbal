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

use MyProject\Proxies\__CG__\OtherProject\Proxies\__CG__\stdClass;

use Doctrine\DBAL\Driver\Statement;
use PDO;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class MssqlStatement implements \IteratorAggregate, Statement
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

    public function __construct($conn, $prepareString)
    {
        $this->_conn = $conn;
        $this->_stmt = $this->_prepare($prepareString);
        if (false === $this->_stmt) {
            throw new MyssqlException($this->_conn->error, $this->_conn->errno);
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

    private function _prepare($prepareString) {
        $stdClass = new \stdClass();
        $stdClass->sql = $prepareString;
        $matches = array();
        $stdClass->param_count = preg_match_all('/[ \(]\?[ ,\)]?/', $prepareString, $matches);
        return $stdClass;
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
                throw new MssqlException("Unkown type: '{$type}'");
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
                throw new MssqlException("Unknown type: '{$type}'");
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
        $sql = $this->_stmt->sql;

        if (null !== $this->_bindedValues) {
            if (null !== $params) {
                $values = array();
                $types = str_repeat('s', count($params));
                $values[0] = $types;

                foreach ($params as &$v) {
                    $values[] =& $v;
                }
                $this->_bindedValues = $values;
            }

            $paramCount = $this->_stmt->param_count;
            $i = 1;
            $bindedValues = &$this->_bindedValues;
            $sql = preg_replace_callback('/\?/', function () use (&$bindedValues, &$i) {
                return "'{$bindedValues[$i++]}'";
            }, $sql);

            /*for ($i = 1; $i <= $paramCount; $i++) {
                $start = strpos($sql, '?');
                $end = $start + 1;
                $sql = substr($sql, 0, $start) . "'" . [$i] . "'" . substr($sql, $end);
            }*/
        }

        $this->_stmt->consulta = $consulta = mssql_query($sql, $this->_conn);

        if ($consulta === false) {
            throw new MssqlException($this->_stmt->error, $this->_stmt->errno);
        }

        if (is_bool($consulta)) {
            return $consulta;
        }

        if (null === $this->_columnNames) {
            $columnNames = array();
            for ($i = 0; $i < mssql_num_fields($consulta); $i++) {
                mssql_field_seek($consulta, $i);

                $col = mssql_fetch_field($consulta);
                $columnNames[] = $col->name;
            }

            $this->_columnNames = $columnNames;
            $this->_rowBindedValues = array_fill(0, count($columnNames), NULL);

/*             $linha = mssql_fetch_array($consulta, MSSQL_NUM);
            foreach ($this->_rowBindedValues as $key => $value) {
                $this->_rowBindedValues[$key] = &$linha[$key];
            } */
        } else {
            $this->_columnNames = false;
        }

        // We have a result.
        if (false !== $this->_columnNames) {
            //$this->_stmt->store_result();
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
        $paramCount = $this->_stmt->param_count;
        for ($i = 1; $i <= $paramCount; $i++) {
            $start = strpos($sql, '?');
            $end = $start + 1;
            $sql = substr($sql, 0, $start) . "'" . $this->_bindedValues[$i] . "'" . substr($sql, $end);
        }


        return call_user_func_array(array($this->_stmt, 'bind_param'), $params);
    }

    /**
     * @return null|false|array
     */
    private function _fetch()
    {
        //FIXME
        $ret = mssql_fetch_array($this->_stmt->consulta, MSSQL_NUM);
        if (is_array($ret)) {
            $values = array();
            foreach ($this->_rowBindedValues as $key => $v) {
                // Mysqli converts them to a scalar type it can fit in.
                $v = $ret[$key];
                $values[] = null === $v ? null : (string) $v;
            }
            return $values;
        }
        return $ret === false ? null : false;
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
            throw new MssqlException($this->_stmt->error, $this->_stmt->errno);
        }

        $fetchStyle = $fetchStyle ?: $this->_defaultFetchStyle;
        switch ($fetchStyle) {
            case PDO::FETCH_NUM:
                return $values;

            case PDO::FETCH_ASSOC:
                return array_combine($this->_columnNames, $values);

            case PDO::FETCH_BOTH:
                var_dump($this->_columnNames);
                $ret = array_combine($this->_columnNames, $values);
                $ret += $values;
                return $ret;

            default:
                throw new MssqlException("Unknown fetch type '{$fetchStyle}'");
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
        if (is_resource($this->_stmt->consulta)) {
            mssql_free_result($this->_stmt->consulta);
        }

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

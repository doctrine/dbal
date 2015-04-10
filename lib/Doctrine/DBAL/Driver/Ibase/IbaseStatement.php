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

namespace Doctrine\DBAL\Driver\Ibase;

use PDO;
use IteratorAggregate;
use Doctrine\DBAL\Driver\Statement;

/**
 * The Interbase/Firebird implementation of the Statement interface based on the ibase-api.
 */
class IbaseStatement implements \IteratorAggregate, Statement
{
    /**
     * @var resource
     */
    protected $_dbh;

    /**
     * @var resource
     */
    protected $_sth;

    /**
     * @var \Doctrine\DBAL\Driver\IBase\IbaseConnection
     */
    protected $_conn;

    /**
     * @var integer
     */
    protected $_defaultPdoFetchMode = PDO::FETCH_BOTH;
    
    /**
     *
     * @var ressource
     */
    public $resCursor = null;

    /**
     * @var array
     */
    protected $queryParams = array();

    /**
     * Creates a new OCI8Statement that uses the given connection handle and SQL statement.
     *
     * @param resource                                  $dbh       The connection handle.
     * @param string                                    $statement The SQL statement.
     * @param \Doctrine\DBAL\Driver\IBase\IbaseConnection $conn
     */
    public function __construct($dbh, $statement, IbaseConnection $conn)
    {
        $this->_sth = ibase_prepare($dbh, $statement);
        $this->_dbh = $dbh;
        $this->_conn = $conn;
    }
    
    public function __destruct()
    {
        if (is_resource($this->resCursor))
        {
            @ibase_free_result($this->resCursor);
            $this->resCursor = null;
        }
        if (is_resource($this->_sth))
        {
            @ibase_free_query($this->_sth);
            $this->_sth = null;
        }
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
        if (!is_numeric($column)) {
            throw new IbaseException("ibase does not support named parameters to queries, use question mark (?) placeholders instead.");
        }

        $this->queryParams[$column-1] = $variable;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        ibase_free_result($this->resCursor);
        $this->resCursor = null;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return ibase_num_fields($this->_sth);
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return ibase_errcode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return array(
            'code' => $this->errorCode(),
            'message' => ibase_errmsg()
        );
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
        
        $callArgs = $this->queryParams;
        array_unshift($callArgs,$this->_sth);
        $this->resCursor=@call_user_func_array('ibase_execute',$callArgs);
        
        if ($this->resCursor === false)
        {
            $this->resCursor = null;
            return false;
        }
        else
        {
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->_defaultFetchMode = $fetchMode;

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
    
    public function fetchObject($aClass = 'StdClass')
    {
        $data = ibase_fetch_row($this->resCursor);
        if (is_array($data))
        {
            $result = new $aClass();
            foreach ($row as $p => $v)
            {
                $result->$p = $v;
            }
        }
        else
            return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        if ($fetchMode == PDO::FETCH_OBJ || $fetchMode == PDO::FETCH_CLASS) {
            return $this->fetchObject();
        }
        else
        {
            switch ($fetchMode)
            {
                case PDO::FETCH_ASSOC:
                {
                    return ibase_fetch_assoc($this->resCursor, IBASE_TEXT);
                    break;
                }
                case PDO::FETCH_NUM:
                {
                    return ibase_fetch_row($this->resCursor, IBASE_TEXT);
                    break;
                }
                case PDO::FETCH_BOTH:
                {
                    $tmpData = ibase_fetch_assoc($this->resCursor, IBASE_TEXT);
                    return array_merge(array_values($tmpData), $tmpData);
                    break;
                }
                default:
                {
                    throw new IbaseException("Fetch mode is not supported!");
                }
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $fetchMode = $fetchMode ?: $this->_defaultFetchMode;

        $result = array();
        while ($row = $this->fetch($fetchMode)) {
          $result[] = $row;
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = ibase_fetch_row($this->resCursor);

        if ($row === false) {
            return false;
        }

        return isset($row[$columnIndex]) ? $row[$columnIndex] : null;
    }

    public function rowCount()
    {
        return ibase_affected_rows($this->_dbh);
    }

}

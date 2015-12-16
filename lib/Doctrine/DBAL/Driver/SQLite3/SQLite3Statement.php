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

namespace Doctrine\DBAL\Driver\SQLite3;

use Doctrine\DBAL\Driver\Statement;

/**
 * SQLite3 implementation of the Statement interface.
 *
 * @since 2.6
 * @author Ben Morel <benjamin.morel@gmail.com>
 * @author Bill Schaller <bill@zeroedin.com>
 */
class SQLite3Statement extends SQLite3Abstract implements \IteratorAggregate, Statement
{
    /**
     * The SQLite3 statement object.
     *
     * @var \SQLite3Stmt
     */
    private $stmt;

    /**
     * The last SQLite3 result object, if the statement has been executed.
     *
     * @var \SQLite3Result|null
     */
    private $result;

    /**
     * The number of rows affected by the last execution.
     *
     * @var integer
     */
    private $rowCount = 0;

    /**
     * The default fetch mode, one of the PDO::FETCH_* constants.
     *
     * @var integer
     */
    private $fetchMode = \PDO::FETCH_BOTH;

    /**
     * An optional argument for the default fetch mode.
     *
     * This argument has a different meaning depending on the value of the fetch_style parameter:
     *
     * - PDO::FETCH_COLUMN: the index of the column to return.
     * - PDO::FETCH_CLASS: the name of the class to instantiate.
     * - PDO::FETCH_FUNC: the function to call.
     *
     * @var integer|string|callable|null
     */
    private $fetchArgument;

    /**
     * The arguments of the class constructor when the fetch_style parameter is PDO::FETCH_CLASS.
     *
     * @var array|null
     */
    private $fetchCtorArgs;

    /**
     * The error code for the last execution of the statement.
     *
     * @var integer
     */
    private $lastErrorCode = 0;

    /**
     * The error message for the last execution of the statement.
     *
     * @var string
     */
    private $lastErrorMessage = 'not an error';

    /**
     * Class constructor.
     *
     * @param \SQLite3     $sqlite3 The SQLite3 connection object.
     * @param \SQLite3Stmt $stmt    The SQLite3 statement object.
     */
    public function __construct(\SQLite3 $sqlite3, \SQLite3Stmt $stmt)
    {
        $this->sqlite3 = $sqlite3;
        $this->stmt    = $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        try {
            if ($type === null) {
                return $this->stmt->bindValue($param, $value);
            }

            return $this->stmt->bindValue($param, $value, $this->convertType($type));
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, & $variable, $type = null, $length = null)
    {
        try {
            if ($type === null) {
                return $this->stmt->bindParam($column, $variable);
            }

            return $this->stmt->bindParam($column, $variable, $this->convertType($type));
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->lastErrorCode;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return [
            null,
            $this->lastErrorCode,
            $this->lastErrorMessage
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        try {
            if ($params) {
                foreach ($params as $key => $value) {
                    $this->bindValue(is_int($key) ? $key + 1 : $key, $value);
                }
            }

            $result = $this->stmt->execute();

            $this->lastErrorCode = $this->sqlite3->lastErrorCode();
            $this->lastErrorMessage = $this->sqlite3->lastErrorMsg();

            $this->result = $result;
            $this->rowCount = $this->sqlite3->changes();
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }

        $this->throwExceptionOnError();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return $this->rowCount;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        return $this->result->finalize();
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        if ($this->result) {
            try {
                return $this->result->numColumns();
            } catch (\Exception $e) {
                throw $this->wrapDriverException($e);
            }
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->fetchMode     = $fetchMode;
        $this->fetchArgument = $arg2;
        $this->fetchCtorArgs = $arg3;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        if (! $this->result) {
            return false;
        }

        if ($fetchMode === null) {
            $fetchMode = $this->fetchMode;
            $argument  = $this->fetchArgument;
            $ctorArgs  = $this->fetchCtorArgs;
        } else {
            $args = func_get_args();
            $argument = isset($args[1]) ? $args[1] : null;
            $ctorArgs = isset($args[2]) ? $args[2] : null;
        }

        try {
            $result = $this->result->fetchArray($this->convertFetchMode($fetchMode));
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }

        if ($result === false) {
            return false;
        }

        if ($fetchMode === \PDO::FETCH_OBJ || $fetchMode === \PDO::FETCH_CLASS) {
            if ($fetchMode === \PDO::FETCH_OBJ) {
                $object = new \StdClass();
            } else {
                $class = new \ReflectionClass($argument);

                if ($ctorArgs === null) {
                    $object = $class->newInstance();
                } else {
                    $object = $class->newInstanceArgs($ctorArgs);
                }
            }

            foreach ($result as $name => $value) {
                $object->$name = $value;
            }

            return $object;
        }

        if ($fetchMode === \PDO::FETCH_COLUMN) {
            $columnIndex = ($argument === null) ? 0 : (int) $argument;

            if (isset($result[$columnIndex])) {
                return $result[$columnIndex];
            }

            return null;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $rows = [];

        if ($this->result) {
            try {
                $this->result->reset();

                for (; ;) {
                    $row = call_user_func_array(array($this, 'fetch'), func_get_args());

                    if ($row === false) {
                        break;
                    }

                    $rows[] = $row;
                }
            } catch (\Exception $e) {
                throw $this->wrapDriverException($e);
            }
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->fetch(\PDO::FETCH_COLUMN, $columnIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->fetchAll());
    }

    /**
     * Converts a PDO type constant to a SQLite3 type constant.
     *
     * @param integer $type The PDO type.
     *
     * @return integer The SQLite3 type.
     *
     * @throws \InvalidArgumentException
     */
    private function convertType($type)
    {
        switch ($type) {
            case \PDO::PARAM_INT:
                return SQLITE3_INTEGER;

            case \PDO::PARAM_STR:
                return SQLITE3_TEXT;

            case \PDO::PARAM_BOOL:
                return SQLITE3_INTEGER;

            case \PDO::PARAM_LOB:
                return SQLITE3_BLOB;

            case \PDO::PARAM_NULL:
                return SQLITE3_NULL;
        }

        throw new \InvalidArgumentException('Unknown type: ' . $type);
    }

    /**
     * Converts a PDO fetch mode constant to a SQLite3 fetch mode constant.
     *
     * @param integer $fetchMode The PDO fetch mode.
     *
     * @return integer The SQLite3 fetch mode.
     */
    private function convertFetchMode($fetchMode)
    {
        switch ($fetchMode) {
            case \PDO::FETCH_NUM:
            case \PDO::FETCH_COLUMN:
                return SQLITE3_NUM;

            case \PDO::FETCH_ASSOC:
            case \PDO::FETCH_OBJ:
            case \PDO::FETCH_CLASS:
            case \PDO::FETCH_FUNC:
                return SQLITE3_ASSOC;

            case \PDO::FETCH_BOTH:
                return SQLITE3_BOTH;
        }

        throw new \InvalidArgumentException('Unknown fetch mode: ' . $fetchMode);
    }
}

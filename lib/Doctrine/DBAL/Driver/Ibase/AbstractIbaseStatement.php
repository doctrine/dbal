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
 * 
 * <b>This Driver/Platform is in Beta state</b>
 * 
 * @author Andreas Prucha, Helicon Software Development <prucha@helicon.co.at>
 */
abstract class AbstractIbaseStatement implements \IteratorAggregate, Statement
{

    /**
     * @var resource|null   Ressource of the prepared statement
     */
    protected $ibaseStatementRc;

    /**
     * @var \Doctrine\DBAL\Driver\IBase\AbstractIbaseConnection
     */
    protected $connection;

    /**
     * @var ressource|null  Query result ressource
     */
    public $ibaseResultRc = null;

    /**
     * The SQL or DDL statement
     * @var string 
     */
    protected $statement = null;

    /**
     * Zero-Based List of parameter bindings
     * @var array
     */
    protected $queryParamBindings = array();

    /**
     * Zero-Based List of parameter binding types
     * @var array
     */
    protected $queryParamTypes = array();

    /**
     * @var integer Default fetch mode set by setFetchMode
     */
    protected $defaultFetchMode = \PDO::FETCH_BOTH;
    /**
     * @var string  Default class to be used by FETCH_CLASS or FETCH_OBJ
     */
    protected $defaultFetchClass = '\stdClass';
    /**
     * @var integer Default column to fetch by FETCH_COLUMN
     */
    protected $defaultFetchColumn = 0;
    /**
     * @var array   Parameters to be passed to constructor in FETCH_CLASS 
     */
    protected $defaultFetchClassConstructorArgs = array();
    /**
     * @var Object  Object used as target by FETCH_INTO  
     */
    protected $defaultFetchInto = null;

    /**
     * Number of rows affected by last execute
     * @var integer 
     */
    protected $affectedRows = 0;
    protected $numFields = false;

    /**
     * Mapping between parameter names and positions
     * 
     * The map is indexed by parameter name including the leading ':'.
     * 
     * Each item contains an array of zero-based parameter positions.
     */
    protected $namedParamsMap = array();

    /**
     * Creates a new OCI8Statement that uses the given connection handle and SQL statement.
     *
     * @param resource                                  $dbh       The connection handle.
     * @param string                                    $statement The SQL statement.
     * @param \Doctrine\DBAL\Driver\IBase\AbstractIbaseConnection $conn
     */
    public function __construct(AbstractIbaseConnection $connection, $statement)
    {
        $this->connection = $connection;
        $this->setStatement($statement);
    }
    
    /**
     * Frees the ressources
     */
    public function __destruct()
    {
        $this->closeCursor();
        if ($this->ibaseStatementRc && is_resource($this->ibaseStatementRc)) {
            @ibase_free_query($this->ibaseStatementRc);
            $this->ibaseStatementRc = null;
        }
    }

    /**
     * Sets and analyzes the statement
     * 
     * @param string $statement
     */
    protected function setStatement($statement)
    {
        $this->statement = $statement;
        $this->namedParamsMap = array();

        $pp = \Doctrine\DBAL\SQLParserUtils::getPlaceholderPositions($statement, false);
        if (!empty($pp)) {
            $pidx = 0; // index-position of the parameter
            $le = 0; // substr start position
            $convertedStatement = '';
            foreach ($pp as $ppos => $pname) {
                $convertedStatement .= substr($statement, $le, $ppos - $le) . '?';
                if (!isset($this->namedParamsMap[':' . $pname])) {
                    $this->namedParamsMap[':' . $pname] = (array)$pidx;
                } else {
                    $this->namedParamsMap[':' . $pname][] = $pidx;
                }
                $le = $ppos + strlen($pname) + 1; // Continue at position after :name
                $pidx++;
            }
            $convertedStatement .= substr($statement, $le);
            $this->statement = $convertedStatement;
        }
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
        $errorCode = $this->errorCode();
        if ($errorCode) {
            return array(
                'code' => $this->errorCode(),
                'message' => ibase_errmsg());
        } else {
            return array('code' => 0, 'message' => null);
        }
    }

    /**
     * Checks ibase_error and raises an exception if an error occured
     */
    protected function checkLastApiCall()
    {
        $lastError = $this->errorInfo();
        if (isset($lastError['code']) && $lastError['code']) {
            throw IbaseException::fromErrorInfo($lastError);
        }
    }

    /**
     * Creates an object and tries to set the object properties in a case insensitive way
     * 
     * If a object is passed, the constructor is not called again
     * 
     * NOTE: This function tries to mimic PDO's behaviour to set the properties *before* calling the constructor if possible.
     * 
     * @param string|object $aClassOrObject Class name. If a object instance is passed, the given object is used
     * @param type $aConstructorArgArray Parameters passed to the constructor 
     * @param type $aPropertyList Associative array of object properties
     * @return object created object or object passed in $aClassOrObject
     */
    protected function createObjectAndSetPropertiesCaseInsenstive($aClassOrObject, array $aConstructorArgArray, array $aPropertyList)
    {
        $callConstructor = false;
        if (is_object($aClassOrObject)) {
            $result = $aClassOrObject;
            $reflector = new \ReflectionObject($aClassOrObject);
        } else {
            if (!is_string($aClassOrObject))
                $aClassOrObject = '\stdClass';
            $classReflector = new \ReflectionClass($aClassOrObject);
            if (method_exists($classReflector, 'newInstanceWithoutConstructor')) {
                $result = $classReflector->newInstanceWithoutConstructor();
                $callConstructor = true;
            } else {
                $result = $classReflector->newInstance($aConstructorArgArray);
            }
            $reflector = new \ReflectionObject($result);
        }

        $propertyReflections = $reflector->getProperties();

        foreach ($aPropertyList as $properyName => $propertyValue) {
            $createNewProperty = true;

            foreach ($propertyReflections as $propertyReflector) /* @var $propertyReflector \ReflectionProperty */ {
                if (strcasecmp($properyName, $propertyReflector->name) == 0) {
                    $propertyReflector->setValue($result, $propertyValue);
                    $createNewProperty = false;
                    break; // ===> BREAK We have found what we want 
                }
            }

            if ($createNewProperty) {
                $result->$properyName = $propertyValue;
            }
        }

        if ($callConstructor) {
            $constructorRefelector = $reflector->getConstructor();
            if ($constructorRefelector) {
                $constructorRefelector->invokeArgs($result, $aConstructorArgArray);
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     * @param type $fetchMode
     * @param type $arg2
     * @param type $arg3
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        switch ($fetchMode) {
            case \PDO::FETCH_OBJ:
            case \PDO::FETCH_CLASS: {
                    $this->defaultFetchMode = $fetchMode;
                    $this->defaultFetchClass = is_string($arg2) ? $arg2 : '\stdClass';
                    $this->defaultFetchClassConstructorArgs = is_array($arg3) ? $arg3 : array();
                    break;
                }
            case \PDO::FETCH_INTO: {
                    $this->defaultFetchMode = $fetchMode;
                    $this->defaultFetchInfo = $arg2;
                }
            case \PDO::FETCH_COLUMN: {
                    $this->defaultFetchMode = $fetchMode;
                    $this->defaultFetchColumn = isset($arg2) ? $arg2 : 0;
                }
            default:
                $this->defaultFetchMode = $fetchMode;
        }
    }

    /**
     * Fetches a single column. 
     * @param type $columnIndex
     * @return boolean|mixed 
     */
    protected function internalFetchColumn($columnIndex = 0)
    {
        $rowData = $this->internalFetchNum();
        if (is_array($rowData)) {
            return (isset($rowData[$columnIndex]) ? $rowData[$columnIndex] : NULL);
        } else {
            return FALSE;
        }
    }

    /**
     * @param type $columnIndex
     * @return type
     */
    protected function internalFetchAllColumn($columnIndex = 0)
    {
        $result = array();
        while ($data = $this->internalFetchColumn()) {
            $result[] = $data;
        }
        return $result;
    }

    /**
     * Fetch associative array
     * @return type
     */
    protected function internalFetchAllAssoc()
    {
        $result = array();
        while ($data = $this->internalFetchAssoc()) {
            $result[] = $data;
        }
        return $result;
    }

    /**
     * Fetches a record into a array
     * @return type
     */
    protected function internalFetchNum()
    {
        return @ibase_fetch_row($this->ibaseResultRc, IBASE_TEXT);
    }

    /**
     * Fetch all records into an array of numeric arrays
     * @return type
     */
    protected function internalFetchAllNum()
    {
        $result = array();
        while ($data = $this->internalFetchNum()) {
            $result[] = $data;
        }
        return $result;
    }

    /**
     * Fetches all records into an array containing arrays with column name and column index 
     * @return array
     */
    protected function internalFetchAllBoth()
    {
        $result = array();
        while ($data = $this->internalFetchBoth()) {
            $result[] = $data;
        }
        return $result;
    }

    /**
     * Fetches into an object
     * 
     * @param object|string $aClassOrObject Object instance or class
     * @param array $constructorArguments   Parameters to pass to the constructor
     * @return object|false                 Result object or false
     */
    protected function internalFetchClassOrObject($aClassOrObject, array $constructorArguments = null)
    {
        $rowData = $this->internalFetchAssoc();
        if (is_array($rowData)) {
            return $this->createObjectAndSetPropertiesCaseInsenstive($aClassOrObject, is_array($constructorArguments) ? $constructorArguments : array(), $rowData);
        } else {
            return $rowData;
        }
    }

    /**
     * Fetches all records into objects
     * 
     * @param object|string $aClassOrObject Object instance or class
     * @param array $constructorArguments   Parameters to pass to the constructor
     * @return object|false                 Result object or false
     */
    protected function internalFetchAllClassOrObjects($aClassOrObject, array $constructorArguments)
    {
        $result = array();
        while ($row = $this->internalFetchClassOrObject($aClassOrObject, $constructorArguments)) {
            if ($row !== false) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /**
     * Fetches the next record into an associative array
     * @return array
     */
    protected function internalFetchAssoc()
    {
        return @ibase_fetch_assoc($this->ibaseResultRc, IBASE_TEXT);
    }

    /**
     * Fetches the next record into an array using column name and index as key
     * @return array|boolean
     */
    protected function internalFetchBoth()
    {
        $tmpData = ibase_fetch_assoc($this->ibaseResultRc, IBASE_TEXT);
        if ($tmpData !== FALSE)
            return array_merge(array_values($tmpData), $tmpData);
        else
            return FALSE;
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
        if (is_object($variable)) {
            $variable = (string) $variable;
        }

        if (is_numeric($column)) {
            $this->queryParamBindings[$column - 1] = &$variable;
            $this->queryParamTypes[$column - 1] = $type;
        } else {
            if (isset($this->namedParamsMap[$column])) {
                foreach ($this->namedParamsMap[$column] as $pp) /* @var integer $pp *zero* based Parameter index */ {
                    $this->queryParamBindings[$pp] = &$variable;
                    $this->queryParamTypes[$pp] = $type;
                }
            } else {
                throw new IbaseException('Cannot bind to unknown parameter ' . $column, null);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        if ($this->ibaseResultRc && is_resource($this->ibaseResultRc)) {
            @ibase_free_result($this->ibaseResultRc);
        }
        $this->ibaseResultRc = null;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->numFields;
    }

    /**
     * Prepares the statement for further use and executes it
     * @result resource|boolean
     */
    protected function doDirectExec()
    {
        $callArgs = $this->queryParamBindings;
        array_unshift($callArgs, $this->connection->getActiveTransactionIbaseRes(), $this->statement);
        return @call_user_func_array('ibase_query', $callArgs);
    }

    /**
     * Prepares the statement for further use and executes it
     * @result resource|boolean
     */
    protected function doExecPrepared()
    {
        if (!$this->ibaseStatementRc || !is_resource($this->ibaseStatementRc)) {
            $this->ibaseStatementRc = @ibase_prepare($this->connection->getActiveTransactionIbaseRes(), $this->statement);
            if (!$this->ibaseStatementRc || !is_resource($this->ibaseStatementRc))
                $this->checkLastApiCall();
        }
        $callArgs = $this->queryParamBindings;
        array_unshift($callArgs, $this->ibaseStatementRc);
        return @call_user_func_array('ibase_execute', $callArgs);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        $this->affectedRows = 0;
        
        // Bind passed parameters

        if (is_array($params) && !empty($params)) {
            $idxShift = array_key_exists(0, $params) ? 1 : 0;
            $hasZeroIndex = array_key_exists(0, $params);
            foreach ($params as $key => $val) {
                $key = (is_numeric($key)) ? $key + $idxShift : $key;
                $this->bindValue($key, $val);
            }
        }
        
        // Execute statement

        if (count($this->queryParamBindings) > 0) {
           $this->ibaseResultRc = $this->doExecPrepared();
        } else {
            $this->ibaseResultRc = $this->doDirectExec();
        }
        
        // Check result
        
        if ($this->ibaseResultRc !== false) {
            // Result seems ok - is either #rows or result handle
            if (is_numeric($this->ibaseResultRc)) {
                $this->affectedRows = $this->ibaseResultRc;
                $this->numFields = @ibase_num_fields($this->ibaseResultRc);
                $this->ibaseResultRc = null;
            } elseif (is_resource($this->ibaseResultRc)) {
                $this->affectedRows = ibase_affected_rows($this->connection->getActiveTransactionIbaseRes());
                $this->numFields = @ibase_num_fields($this->ibaseResultRc);
            }

            // As the ibase-api does not have an auto-commit-mode, autocommit is simulated by calling the
            // function autoCommit of the connection
            $this->connection->autoCommit();
        } else {
            // Error
            $this->checkLastApiCall();
        }


        if ($this->ibaseResultRc === false) {
            $this->ibaseResultRc = null;
            return false;
        } else {
            return true;
        }
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
     * Fetches a single row into a object
     * 
     * @param object|string $fetchIntoObjectOrClass Object class to create or object to update
     * 
     * @return boolean
     */
    public function fetchObject($fetchIntoObjectOrClass = '\stdClass')
    {
        return $this->internalFetchClassOrObject($fetchIntoObjectOrClass);
    }

    /**
     * {@inheritdoc}
     * @param int $fetchMode
     * @param null|string $optArg1 
     */
    public function fetch($fetchMode = null, $optArg1 = null)
    {
        $fetchMode !== null || $fetchMode = $this->defaultFetchMode;

        switch ($fetchMode) {
            case \PDO::FETCH_OBJ:
            case \PDO::FETCH_CLASS: {
                    return $this->internalFetchClassOrObject(isset($optArg1) ? $optArg1 : $this->defaultFetchClass, $this->defaultFetchClassConstructorArgs);
                }
            case \PDO::FETCH_INTO: {
                    return $this->internalFetchClassOrObject(isset($optArg1) ? $optArg1 : $this->defaultFetchInto, array());
                }
            case \PDO::FETCH_ASSOC: {
                    return $this->internalFetchAssoc();
                    break;
                }
            case \PDO::FETCH_NUM: {
                    return $this->internalFetchNum();
                    break;
                }
            case \PDO::FETCH_BOTH: {
                    return $this->internalFetchBoth();
                }
            default: {
                    throw new IbaseException('Fetch mode ' . $fetchMode . ' not supported by this driver in ' . __METHOD__);
                }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        $fetchMode !== null || $fetchMode = $this->defaultFetchMode;

        switch ($fetchMode) {
            case \PDO::FETCH_CLASS:
            case \PDO::FETCH_OBJ: {
                    return $this->internalFetchAllClassOrObjects(
                                    $fetchArgument == null ? $this->defaultFetchClass : $fetchArgument, $ctorArgs == null ? $this->defaultFetchClassConstructorArgs : $ctorArgs);
                    break;
                }
            case \PDO::FETCH_COLUMN: {
                    return $this->internalFetchAllColumn(
                                    $fetchArgument == null ? 0 : $fetchArgument);
                    break;
                }
            case \PDO::FETCH_BOTH: {
                    return $this->internalFetchAllBoth();
                }
            case \PDO::FETCH_ASSOC: {
                    return $this->internalFetchAllAssoc();
                }
            case \PDO::FETCH_NUM: {
                    return $this->internalFetchAllNum();
                }
            default: {
                    throw new IbaseException('Fetch mode ' . $fetchMode . ' not supported by this driver in ' . __METHOD__);
                }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->internalFetchColumn($columnIndex);
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount()
    {
        return $this->affectedRows;
    }

}

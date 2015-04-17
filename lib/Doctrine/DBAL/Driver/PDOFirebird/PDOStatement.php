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

namespace Doctrine\DBAL\Driver\PDOFirebird;

/**
 * Statements for the PDO-Based firebird driver
 *
 * @author Andreas Prucha, Helicon Software Development <prucha@helicon.co.at>
 * @experimental
 */
class PDOStatement extends \Doctrine\DBAL\Driver\PDOStatement
{

    protected $defaultFetchMode = \PDO::FETCH_BOTH;
    protected $defaultFetchClass = '\stdClass';
    protected $defaultFetchColumn = 0;
    protected $defaultFetchClassConstructorArgs = array();
    protected $defaultFetchInto = null;

    /**
     * @var \Doctrine\DBAL\Driver\IBase\IbaseConnection
     */
    protected $connection;

    /**
     * {@inheritDoc}
     * 
     * @param \Doctrine\DBAL\Driver\PDOFirebird\PDOConnection $connection
     */
    protected function __construct(PDOConnection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
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
        parent::setFetchMode($fetchMode, $arg2, $arg3);
    }

    protected function internalFetchColumn($columnIndex = 0, $cursorOrientation = null, $cursorOffset = null)
    {
        $rowData = parent::fetch(\PDO::FETCH_NUM, $cursorOrientation, $cursorOffset);
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
        while ($data = parent::fetch(\PDO::FETCH_NUM)) {
            if (is_array($data) && isset($data[$columnIndex])) {
                $result[] = $data[$columnIndex];
            } else {
                $result[] = null;
            }
        }
        return $result;
    }

    protected function internalFetchClassOrObject($aClassOrObject, array $constructorArguments = null, $cursorOrientation = null, $cursorOffset = null)
    {
        $rowData = parent::fetch(\PDO::FETCH_ASSOC, $cursorOrientation, $cursorOffset);
        if (is_array($rowData)) {
            return $this->createObjectAndSetPropertiesCaseInsenstive($aClassOrObject, is_array($constructorArguments) ? $constructorArguments : array(), $rowData);
        } else {
            return $rowData;
        }
    }

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

    protected function internalFetchAll($fetchMode, $arg1, $arg2)
    {
        
    }

    public function fetch($fetchMode = null, $cursorOrientation = null, $cursorOffset = null)
    {
        $fetchMode !== null || $fetchMode = $this->defaultFetchMode;

        switch ($fetchMode) {
            case \PDO::FETCH_COLUMN: {
                    return $this->internalFetchColumn($this->defaultFetchColumn, $cursorOrientation, $cursorOffset);
                }
            case \PDO::FETCH_INTO: {
                    return $this->internalFetchClassOrObject($this->defaultFetchInto, $cursorOrientation, $cursorOffset);
                }
            case \PDO::FETCH_CLASS:;
            case \PDO::FETCH_OBJ: {
                    return $this->internalFetchClassOrObject($this->defaultFetchClass, $cursorOrientation, $cursorOffset);
                }
            default: {
                    return parent::fetch($fetchMode, $cursorOrientation, $cursorOffset);
                }
        }
    }

    /**
     * {@inheritDoc}
     * 
     * @param type $fetchMode
     * @param type $fetchArgument If $fetchMode is \PDO::FETCH_CLASS, the class name can be passed
     * @param type $ctorArgs
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
            default: {
                    return parent::fetchAll($fetchMode, $fetchArgument, $ctorArgs);
                }
        }
    }

    /**
     * {@inheritDoc}
     * 
     * NOTE: Contrary to the standard implementation, properties are set case-insensitive in order to overcome
     * Firebirds preference for uppercase field names. This means, if the class declares a property $fooBar,
     * it will be used, if the database column is named FOOBAR. If the property is not declared in the class,
     * the original case is used. 
     * 
     * @param type $class_name
     * @param array $ctor_args
     */
    public function fetchObject($class_name = NULL, $ctor_args = NULL)
    {
        return $this->internalFetchClassOrObject($class_name == null ? '\stdClass' : $class_name, $ctor_args);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->internalFetchColumn($columnIndex);
    }

    /**
     * {@inheritDoc}
     */
    public function execute($params = null)
    {
        try {
            $result = parent::execute($params);
            try {
                if ($result) {
                    if ($result === true) {
                        $this->connection->commitRetainIfOutsideTransaction();
                    }
                } else {
                    $this->connection->rollbackRetainIfOutsideTransaction();
                }
            } catch (Exception $ex) {
                // Ignore it
            }
        } catch (Exception $ex) {
            try {
                $this->connection->rollbackRetainIfOutsideTransaction();
            } catch (Exception $ex) {
                // Ignore it
            }
            throw $ex;
        }
        return $result;
    }

}

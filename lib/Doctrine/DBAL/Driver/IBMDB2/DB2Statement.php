<?php

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;

class DB2Statement implements \IteratorAggregate, Statement
{
    /**
     * @var resource
     */
    private $_stmt;

    /**
     * @var array
     */
    private $_bindParam = [];

    /**
     * @var string Name of the default class to instantiate when fetching class instances.
     */
    private $defaultFetchClass = '\stdClass';

    /**
     * @var string Constructor arguments for the default class to instantiate when fetching class instances.
     */
    private $defaultFetchClassCtorArgs = [];

    /**
     * @var integer
     */
    private $_defaultFetchMode = FetchMode::MIXED;

    /**
     * Indicates whether the statement is in the state when fetching results is possible
     *
     * @var bool
     */
    private $result = false;

    /**
     * DB2_BINARY, DB2_CHAR, DB2_DOUBLE, or DB2_LONG
     *
     * @var array
     */
    static private $_typeMap = [
        ParameterType::INTEGER => DB2_LONG,
        ParameterType::STRING => DB2_CHAR,
    ];

    /**
     * @param resource $stmt
     */
    public function __construct($stmt)
    {
        $this->_stmt = $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        $this->_bindParam[$column] =& $variable;

        if ($type && isset(self::$_typeMap[$type])) {
            $type = self::$_typeMap[$type];
        } else {
            $type = DB2_CHAR;
        }

        if (!db2_bind_param($this->_stmt, $column, "variable", DB2_PARAM_IN, $type)) {
            throw new DB2Exception(db2_stmt_errormsg());
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        if ( ! $this->_stmt) {
            return false;
        }

        $this->_bindParam = [];

        if (!db2_free_result($this->_stmt)) {
            return false;
        }

        $this->result = false;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        if ( ! $this->_stmt) {
            return false;
        }

        return db2_num_fields($this->_stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return db2_stmt_error();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return [
            db2_stmt_errormsg(),
            db2_stmt_error(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ( ! $this->_stmt) {
            return false;
        }

        if ($params === null) {
            ksort($this->_bindParam);

            $params = [];

            foreach ($this->_bindParam as $column => $value) {
                $params[] = $value;
            }
        }

        $retval = @db2_execute($this->_stmt, $params);

        if ($retval === false) {
            throw new DB2Exception(db2_stmt_errormsg());
        }

        $this->result = true;

        return $retval;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, ...$args)
    {
        $this->_defaultFetchMode = $fetchMode;

        if (isset($args[0])) {
            $this->defaultFetchClass = $args[0];
        }

        if (isset($args[1])) {
            $this->defaultFetchClassCtorArgs = (array) $args[2];
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, ...$args)
    {
        // do not try fetching from the statement if it's not expected to contain result
        // in order to prevent exceptional situation
        if (!$this->result) {
            return false;
        }

        $fetchMode = $fetchMode ?: $this->_defaultFetchMode;
        switch ($fetchMode) {
            case FetchMode::MIXED:
                return db2_fetch_both($this->_stmt);

            case FetchMode::ASSOCIATIVE:
                return db2_fetch_assoc($this->_stmt);

            case FetchMode::CUSTOM_OBJECT:
                $className = $this->defaultFetchClass;
                $ctorArgs  = $this->defaultFetchClassCtorArgs;

                if (count($args) > 0) {
                    $className = $args[0];
                    $ctorArgs  = $args[1] ?? [];
                }

                $result = db2_fetch_object($this->_stmt);

                if ($result instanceof \stdClass) {
                    $result = $this->castObject($result, $className, $ctorArgs);
                }

                return $result;

            case FetchMode::NUMERIC:
                return db2_fetch_array($this->_stmt);

            case FetchMode::STANDARD_OBJECT:
                return db2_fetch_object($this->_stmt);

            default:
                throw new DB2Exception('Given Fetch-Style ' . $fetchMode . ' is not supported.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, ...$args)
    {
        $rows = [];

        switch ($fetchMode) {
            case FetchMode::CUSTOM_OBJECT:
                while (($row = $this->fetch($fetchMode, ...$args)) !== false) {
                    $rows[] = $row;
                }
                break;
            case FetchMode::COLUMN:
                while (($row = $this->fetchColumn()) !== false) {
                    $rows[] = $row;
                }
                break;
            default:
                while (($row = $this->fetch($fetchMode)) !== false) {
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
        $row = $this->fetch(FetchMode::NUMERIC);

        if (false === $row) {
            return false;
        }

        return isset($row[$columnIndex]) ? $row[$columnIndex] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return (@db2_num_rows($this->_stmt)) ? : 0;
    }

    /**
     * Casts a stdClass object to the given class name mapping its' properties.
     *
     * @param \stdClass     $sourceObject     Object to cast from.
     * @param string|object $destinationClass Name of the class or class instance to cast to.
     * @param array         $ctorArgs         Arguments to use for constructing the destination class instance.
     *
     * @return object
     *
     * @throws DB2Exception
     */
    private function castObject(\stdClass $sourceObject, $destinationClass, array $ctorArgs = [])
    {
        if ( ! is_string($destinationClass)) {
            if ( ! is_object($destinationClass)) {
                throw new DB2Exception(sprintf(
                    'Destination class has to be of type string or object, %s given.', gettype($destinationClass)
                ));
            }
        } else {
            $destinationClass = new \ReflectionClass($destinationClass);
            $destinationClass = $destinationClass->newInstanceArgs($ctorArgs);
        }

        $sourceReflection           = new \ReflectionObject($sourceObject);
        $destinationClassReflection = new \ReflectionObject($destinationClass);
        /** @var \ReflectionProperty[] $destinationProperties */
        $destinationProperties      = array_change_key_case($destinationClassReflection->getProperties(), \CASE_LOWER);

        foreach ($sourceReflection->getProperties() as $sourceProperty) {
            $sourceProperty->setAccessible(true);

            $name  = $sourceProperty->getName();
            $value = $sourceProperty->getValue($sourceObject);

            // Try to find a case-matching property.
            if ($destinationClassReflection->hasProperty($name)) {
                $destinationProperty = $destinationClassReflection->getProperty($name);

                $destinationProperty->setAccessible(true);
                $destinationProperty->setValue($destinationClass, $value);

                continue;
            }

            $name = strtolower($name);

            // Try to find a property without matching case.
            // Fallback for the driver returning either all uppercase or all lowercase column names.
            if (isset($destinationProperties[$name])) {
                $destinationProperty = $destinationProperties[$name];

                $destinationProperty->setAccessible(true);
                $destinationProperty->setValue($destinationClass, $value);

                continue;
            }

            $destinationClass->$name = $value;
        }

        return $destinationClass;
    }
}

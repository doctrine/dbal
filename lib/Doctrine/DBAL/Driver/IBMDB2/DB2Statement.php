<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;
use stdClass;
use function array_change_key_case;
use function array_key_exists;
use function assert;
use function count;
use function db2_bind_param;
use function db2_execute;
use function db2_fetch_array;
use function db2_fetch_assoc;
use function db2_fetch_both;
use function db2_fetch_object;
use function db2_free_result;
use function db2_num_fields;
use function db2_num_rows;
use function error_get_last;
use function fclose;
use function fwrite;
use function gettype;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;
use function ksort;
use function sprintf;
use function stream_copy_to_stream;
use function stream_get_meta_data;
use function strtolower;
use function tmpfile;
use const CASE_LOWER;
use const DB2_BINARY;
use const DB2_CHAR;
use const DB2_LONG;
use const DB2_PARAM_FILE;
use const DB2_PARAM_IN;

final class DB2Statement implements IteratorAggregate, Statement
{
    /** @var resource */
    private $stmt;

    /** @var mixed[] */
    private $bindParam = [];

    /**
     * Map of LOB parameter positions to the tuples containing reference to the variable bound to the driver statement
     * and the temporary file handle bound to the underlying statement
     *
     * @var mixed[][]
     */
    private $lobs = [];

    /** @var string Name of the default class to instantiate when fetching class instances. */
    private $defaultFetchClass = '\stdClass';

    /** @var mixed[] Constructor arguments for the default class to instantiate when fetching class instances. */
    private $defaultFetchClassCtorArgs = [];

    /** @var int */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * Indicates whether the statement is in the state when fetching results is possible
     *
     * @var bool
     */
    private $result = false;

    /**
     * @param resource $stmt
     */
    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING) : void
    {
        $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null) : void
    {
        assert(is_int($param));

        switch ($type) {
            case ParameterType::INTEGER:
                $this->bind($param, $variable, DB2_PARAM_IN, DB2_LONG);
                break;

            case ParameterType::LARGE_OBJECT:
                if (isset($this->lobs[$param])) {
                    [, $handle] = $this->lobs[$param];
                    fclose($handle);
                }

                $handle = $this->createTemporaryFile();
                $path   = stream_get_meta_data($handle)['uri'];

                $this->bind($param, $path, DB2_PARAM_FILE, DB2_BINARY);

                $this->lobs[$param] = [&$variable, $handle];
                break;

            default:
                $this->bind($param, $variable, DB2_PARAM_IN, DB2_CHAR);
                break;
        }
    }

    public function closeCursor() : void
    {
        $this->bindParam = [];

        if (! $this->result) {
            return;
        }

        db2_free_result($this->stmt);

        $this->result = false;
    }

    public function columnCount() : int
    {
        return db2_num_fields($this->stmt) ?: 0;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null) : void
    {
        if ($params === null) {
            ksort($this->bindParam);

            $params = [];

            foreach ($this->bindParam as $column => $value) {
                $params[] = $value;
            }
        }

        foreach ($this->lobs as [$source, $target]) {
            if (is_resource($source)) {
                $this->copyStreamToStream($source, $target);

                continue;
            }

            $this->writeStringToStream($source, $target);
        }

        $retval = db2_execute($this->stmt, $params);

        foreach ($this->lobs as [, $handle]) {
            fclose($handle);
        }

        $this->lobs = [];

        if ($retval === false) {
            throw DB2Exception::fromStatementError($this->stmt);
        }

        $this->result = true;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode(int $fetchMode, ...$args) : void
    {
        $this->defaultFetchMode = $fetchMode;

        if (isset($args[0])) {
            $this->defaultFetchClass = $args[0];
        }

        if (! isset($args[1])) {
            return;
        }

        $this->defaultFetchClassCtorArgs = (array) $args[2];
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
    public function fetch(?int $fetchMode = null, ...$args)
    {
        // do not try fetching from the statement if it's not expected to contain result
        // in order to prevent exceptional situation
        if (! $this->result) {
            return false;
        }

        $fetchMode = $fetchMode ?: $this->defaultFetchMode;
        switch ($fetchMode) {
            case FetchMode::COLUMN:
                return $this->fetchColumn();

            case FetchMode::MIXED:
                return db2_fetch_both($this->stmt);

            case FetchMode::ASSOCIATIVE:
                return db2_fetch_assoc($this->stmt);

            case FetchMode::CUSTOM_OBJECT:
                $className = $this->defaultFetchClass;
                $ctorArgs  = $this->defaultFetchClassCtorArgs;

                if (count($args) > 0) {
                    $className = $args[0];
                    $ctorArgs  = $args[1] ?? [];
                }

                $result = db2_fetch_object($this->stmt);

                if ($result instanceof stdClass) {
                    $result = $this->castObject($result, $className, $ctorArgs);
                }

                return $result;

            case FetchMode::NUMERIC:
                return db2_fetch_array($this->stmt);

            case FetchMode::STANDARD_OBJECT:
                return db2_fetch_object($this->stmt);

            default:
                throw new DB2Exception('Given Fetch-Style ' . $fetchMode . ' is not supported.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null, ...$args) : array
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
    public function fetchColumn(int $columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        if ($row === false) {
            return false;
        }

        if (! array_key_exists($columnIndex, $row)) {
            throw InvalidColumnIndex::new($columnIndex, count($row));
        }

        return $row[$columnIndex];
    }

    public function rowCount() : int
    {
        return @db2_num_rows($this->stmt) ? : 0;
    }

    /**
     * @param int   $position Parameter position
     * @param mixed $variable
     *
     * @throws DB2Exception
     */
    private function bind(int $position, &$variable, int $parameterType, int $dataType) : void
    {
        $this->bindParam[$position] =& $variable;

        if (! db2_bind_param($this->stmt, $position, 'variable', $parameterType, $dataType)) {
            throw DB2Exception::fromStatementError($this->stmt);
        }
    }

    /**
     * Casts a stdClass object to the given class name mapping its' properties.
     *
     * @param stdClass      $sourceObject     Object to cast from.
     * @param string|object $destinationClass Name of the class or class instance to cast to.
     * @param mixed[]       $ctorArgs         Arguments to use for constructing the destination class instance.
     *
     * @throws DB2Exception
     */
    private function castObject(stdClass $sourceObject, $destinationClass, array $ctorArgs = []) : object
    {
        if (! is_string($destinationClass)) {
            if (! is_object($destinationClass)) {
                throw new DB2Exception(sprintf(
                    'Destination class has to be of type string or object, %s given.',
                    gettype($destinationClass)
                ));
            }
        } else {
            $destinationClass = new ReflectionClass($destinationClass);
            $destinationClass = $destinationClass->newInstanceArgs($ctorArgs);
        }

        $sourceReflection           = new ReflectionObject($sourceObject);
        $destinationClassReflection = new ReflectionObject($destinationClass);
        /** @var ReflectionProperty[] $destinationProperties */
        $destinationProperties = array_change_key_case($destinationClassReflection->getProperties(), CASE_LOWER);

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

    /**
     * @return resource
     *
     * @throws DB2Exception
     */
    private function createTemporaryFile()
    {
        $handle = @tmpfile();

        if ($handle === false) {
            throw new DB2Exception('Could not create temporary file: ' . error_get_last()['message']);
        }

        return $handle;
    }

    /**
     * @param resource $source
     * @param resource $target
     *
     * @throws DB2Exception
     */
    private function copyStreamToStream($source, $target) : void
    {
        if (@stream_copy_to_stream($source, $target) === false) {
            throw new DB2Exception('Could not copy source stream to temporary file: ' . error_get_last()['message']);
        }
    }

    /**
     * @param resource $target
     *
     * @throws DB2Exception
     */
    private function writeStringToStream(string $string, $target) : void
    {
        if (@fwrite($target, $string) === false) {
            throw new DB2Exception('Could not write string to temporary file: ' . error_get_last()['message']);
        }
    }
}

<?php

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\IBMDB2\Exception\CannotCopyStreamToStream;
use Doctrine\DBAL\Driver\IBMDB2\Exception\CannotCreateTemporaryFile;
use Doctrine\DBAL\Driver\IBMDB2\Exception\StatementError;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

use function assert;
use function db2_bind_param;
use function db2_execute;
use function error_get_last;
use function fclose;
use function is_int;
use function is_resource;
use function stream_copy_to_stream;
use function stream_get_meta_data;
use function tmpfile;

use const DB2_BINARY;
use const DB2_CHAR;
use const DB2_LONG;
use const DB2_PARAM_FILE;
use const DB2_PARAM_IN;

final class Statement implements StatementInterface
{
    /** @var resource */
    private $stmt;

    /** @var mixed[] */
    private $parameters = [];

    /**
     * Map of LOB parameter positions to the tuples containing reference to the variable bound to the driver statement
     * and the temporary file handle bound to the underlying statement
     *
     * @var array<int,string|resource|null>
     */
    private $lobs = [];

    /**
     * @internal The statement can be only instantiated by its driver connection.
     *
     * @param resource $stmt
     */
    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        assert(is_int($param));

        return $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        assert(is_int($param));

        switch ($type) {
            case ParameterType::INTEGER:
                $this->bind($param, $variable, DB2_PARAM_IN, DB2_LONG);
                break;

            case ParameterType::LARGE_OBJECT:
                $this->lobs[$param] = &$variable;
                break;

            default:
                $this->bind($param, $variable, DB2_PARAM_IN, DB2_CHAR);
                break;
        }

        return true;
    }

    /**
     * @param int   $position Parameter position
     * @param mixed $variable
     *
     * @throws Exception
     */
    private function bind($position, &$variable, int $parameterType, int $dataType): void
    {
        $this->parameters[$position] =& $variable;

        if (! db2_bind_param($this->stmt, $position, '', $parameterType, $dataType)) {
            throw StatementError::new($this->stmt);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null): ResultInterface
    {
        $handles = $this->bindLobs();

        $result = @db2_execute($this->stmt, $params ?? $this->parameters);

        foreach ($handles as $handle) {
            fclose($handle);
        }

        $this->lobs = [];

        if ($result === false) {
            throw StatementError::new($this->stmt);
        }

        return new Result($this->stmt);
    }

    /**
     * @return list<resource>
     *
     * @throws Exception
     */
    private function bindLobs(): array
    {
        $handles = [];

        foreach ($this->lobs as $param => $value) {
            if (is_resource($value)) {
                $handle = $handles[] = $this->createTemporaryFile();
                $path   = stream_get_meta_data($handle)['uri'];

                $this->copyStreamToStream($value, $handle);

                $this->bind($param, $path, DB2_PARAM_FILE, DB2_BINARY);
            } else {
                $this->bind($param, $value, DB2_PARAM_IN, DB2_CHAR);
            }
        }

        return $handles;
    }

    /**
     * @return resource
     *
     * @throws Exception
     */
    private function createTemporaryFile()
    {
        $handle = @tmpfile();

        if ($handle === false) {
            throw CannotCreateTemporaryFile::new(error_get_last());
        }

        return $handle;
    }

    /**
     * @param resource $source
     * @param resource $target
     *
     * @throws Exception
     */
    private function copyStreamToStream($source, $target): void
    {
        if (@stream_copy_to_stream($source, $target) === false) {
            throw CannotCopyStreamToStream::new(error_get_last());
        }
    }
}

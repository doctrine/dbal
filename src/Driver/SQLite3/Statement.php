<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLite3;

use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use SQLite3;
use SQLite3Stmt;

use function assert;

use const SQLITE3_BLOB;
use const SQLITE3_INTEGER;
use const SQLITE3_NULL;
use const SQLITE3_TEXT;

final class Statement implements StatementInterface
{
    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(
        private readonly SQLite3 $connection,
        private readonly SQLite3Stmt $statement,
    ) {
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->statement->bindValue($param, $value, $this->convertParamType($type));
    }

    public function execute(): Result
    {
        try {
            $result = $this->statement->execute();
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        assert($result !== false);

        return new Result($result, $this->connection->changes());
    }

    private function convertParamType(ParameterType $type): int
    {
        return match ($type) {
            ParameterType::NULL => SQLITE3_NULL,
            ParameterType::INTEGER, ParameterType::BOOLEAN => SQLITE3_INTEGER,
            ParameterType::STRING, ParameterType::ASCII => SQLITE3_TEXT,
            ParameterType::BINARY, ParameterType::LARGE_OBJECT => SQLITE3_BLOB,
        };
    }
}

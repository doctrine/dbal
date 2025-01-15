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
    private const TYPE_BLOB    = SQLITE3_BLOB;
    private const TYPE_INTEGER = SQLITE3_INTEGER;
    private const TYPE_NULL    = SQLITE3_NULL;
    private const TYPE_TEXT    = SQLITE3_TEXT;

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

    /** @phpstan-return self::TYPE_* */
    private function convertParamType(ParameterType $type): int
    {
        return match ($type) {
            ParameterType::NULL => self::TYPE_NULL,
            ParameterType::INTEGER, ParameterType::BOOLEAN => self::TYPE_INTEGER,
            ParameterType::STRING, ParameterType::ASCII => self::TYPE_TEXT,
            ParameterType::BINARY, ParameterType::LARGE_OBJECT => self::TYPE_BLOB,
        };
    }
}

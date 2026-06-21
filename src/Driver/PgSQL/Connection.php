<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PgSQL;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\SQL\Parser;
use Override;
use PgSql\Connection as PgSqlConnection;

use function assert;
use function pg_close;
use function pg_escape_literal;
use function pg_get_result;
use function pg_last_error;
use function pg_result_error;
use function pg_send_prepare;
use function pg_send_query;
use function pg_version;
use function uniqid;

final readonly class Connection implements ConnectionInterface
{
    private Parser $parser;

    public function __construct(private PgSqlConnection $connection)
    {
        $this->parser = new Parser(false);
    }

    public function __destruct()
    {
        // @phpstan-ignore isset.initializedProperty
        if (isset($this->connection)) {
            @pg_close($this->connection);
        }
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
        $visitor = new ConvertParameters();

        $this->parser->parse($sql, $visitor);

        $statementName = uniqid('dbal', true);
        if (@pg_send_prepare($this->connection, $statementName, $visitor->getSQL()) !== true) {
            throw new Exception(pg_last_error($this->connection));
        }

        $result = @pg_get_result($this->connection);
        assert($result !== false);

        if ((bool) pg_result_error($result)) {
            throw Exception::fromResult($result);
        }

        return new Statement($this->connection, $statementName, $visitor->getParameterMap());
    }

    #[Override]
    public function query(string $sql): Result
    {
        if (@pg_send_query($this->connection, $sql) !== true) {
            throw new Exception(pg_last_error($this->connection));
        }

        $result = @pg_get_result($this->connection);
        assert($result !== false);

        if ((bool) pg_result_error($result)) {
            throw Exception::fromResult($result);
        }

        return new Result($result);
    }

    #[Override]
    public function quote(string $value): string
    {
        $quotedValue = pg_escape_literal($this->connection, $value);
        assert($quotedValue !== false);

        return $quotedValue;
    }

    #[Override]
    public function exec(string $sql): int
    {
        return $this->query($sql)->rowCount();
    }

    #[Override]
    public function lastInsertId(): int|string
    {
        try {
            return $this->query('SELECT LASTVAL()')->fetchOne();
        } catch (Exception $exception) {
            if ($exception->getSQLState() === '55000') {
                throw NoIdentityValue::new($exception);
            }

            throw $exception;
        }
    }

    #[Override]
    public function beginTransaction(): void
    {
        $this->exec('BEGIN');
    }

    #[Override]
    public function commit(): void
    {
        $this->exec('COMMIT');
    }

    #[Override]
    public function rollBack(): void
    {
        $this->exec('ROLLBACK');
    }

    #[Override]
    public function getServerVersion(): string
    {
        return (string) pg_version($this->connection)['server'];
    }

    #[Override]
    public function getNativeConnection(): PgSqlConnection
    {
        return $this->connection;
    }
}

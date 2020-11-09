<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Logging\SQLLogger;

use function microtime;

class LockModeSQLLogger implements SQLLogger
{
    /** @var int */
    private $cid;

    /** @var list<array{cid: int, time: float, sql: string}> */
    private $queries = [];

    public function __construct(int $cid)
    {
        $this->cid = $cid;
    }

    /**
     * @inheritDoc
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->queries[] = [
            'cid' => $this->cid,
            'time' => microtime(true),
            'sql' => $sql,
        ];
    }

    public function stopQuery(): void
    {
    }

    /**
     * @return list<array{cid: int, time: float, sql: string}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

use function implode;
use function ksort;
use function ltrim;
use function rtrim;
use function trim;

/** @internal */
final class QueryLockBuilder
{
    public const FOR_UPDATE  = 'FOR_UPDATE';
    public const SKIP_LOCKED = 'SKIP_LOCKED';

    private AbstractPlatform $platform;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    public function isLocatedAfterFrom(): bool
    {
        return $this->isSQLServerPlatform();
    }

    public function isLocatedAtTheEnd(): bool
    {
        return ! $this->isLocatedAfterFrom();
    }

    public function getLocksSql(string ...$lockList): string
    {
        $locksSql = [];
        foreach ($lockList as $lock) {
            switch ($lock) {
                case self::FOR_UPDATE:
                    $locksSql[0] = $this->platform->getForUpdateSQL();
                    break;
                case self::SKIP_LOCKED:
                    $locksSql[1] = $this->platform->getSkipLockedSQL();
                    break;
            }
        }

        ksort($locksSql);

        return trim(
            $this->isSQLServerPlatform()
            ? $this->mergeSQLServerLocks(...$locksSql)
            : implode(' ', $locksSql),
        );
    }

    private function isSQLServerPlatform(): bool
    {
        return $this->platform instanceof SQLServerPlatform;
    }

    private function mergeSQLServerLocks(string ...$locksSql): string
    {
        foreach ($locksSql as $key => $lockSql) {
            $locksSql[$key] = rtrim(ltrim($lockSql, 'WITH ('), ')');
        }

        return 'WITH (' . implode(', ', $locksSql) . ')';
    }
}

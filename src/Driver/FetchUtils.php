<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

/**
 * @internal
 */
final class FetchUtils
{
    /**
     * @return mixed|false
     *
     * @throws DriverException
     */
    public static function fetchOne(ResultStatement $stmt)
    {
        $row = $stmt->fetchNumeric();

        if ($row === false) {
            return false;
        }

        return $row[0];
    }

    /**
     * @return array<int,array<int,mixed>>
     *
     * @throws DriverException
     */
    public static function fetchAllNumeric(ResultStatement $stmt) : array
    {
        $rows = [];

        while (($row = $stmt->fetchNumeric()) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     *
     * @throws DriverException
     */
    public static function fetchAllAssociative(ResultStatement $stmt) : array
    {
        $rows = [];

        while (($row = $stmt->fetchAssociative()) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int,mixed>
     *
     * @throws DriverException
     */
    public static function fetchColumn(ResultStatement $stmt) : array
    {
        $rows = [];

        while (($row = $stmt->fetchOne()) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }
}

<?php

namespace Doctrine\Tests\DBAL\Performance;

use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalPerformanceTestCase;

/**
 * @group performance
 */
class TypeConversionPerformanceTest extends DbalPerformanceTestCase
{
    /**
     * @throws DBALException
     *
     * @dataProvider itemCountProvider
     */
    public function testDateTimeTypeConversionPerformance(int $count) : void
    {
        $value    = new DateTime();
        $type     = Type::getType('datetime');
        $platform = $this->connection->getDatabasePlatform();
        $this->startTiming();
        for ($i = 0; $i < $count; $i++) {
            $type->convertToDatabaseValue($value, $platform);
        }
        $this->stopTiming();
    }

    /**
     * @return mixed[][]
     */
    public static function itemCountProvider() : iterable
    {
        return [
            '100 items' => [100],
            '1000 items' => [1000],
            '10000 items' => [10000],
            '100000 items' => [100000],
        ];
    }
}

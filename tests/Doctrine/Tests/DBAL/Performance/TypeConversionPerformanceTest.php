<?php

namespace Doctrine\Tests\DBAL\Performance;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalPerformanceTestCase;

/**
 * Class TypeConversionPerformanceTest
 * @package Doctrine\Tests\DBAL\Performance
 * @author Bill Schaller
 * @group performance
 */
class TypeConversionPerformanceTest extends DbalPerformanceTestCase
{
    public function testDateTimeTypeConversionPerformance100000Items()
    {
        $value = new \DateTime;
        $start = microtime(true);
        $type = Type::getType("datetime");
        $platform = $this->_conn->getDatabasePlatform();
        for ($i = 0; $i < 100000; $i++) {
            $type->convertToDatabaseValue($value, $platform);
        }
        echo __FUNCTION__ . " - " . (microtime(true) - $start) . " seconds" . PHP_EOL;
    }
}

<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\Tests\DBAL\Component;
use Doctrine\Tests\DBAL\Ticker;
use Doctrine\Tests\DBAL\Functional;

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Dbal_Platforms_AllTests::main');
}

require_once __DIR__ . '/../TestInit.php';

class AllTests
{
    public static function main()
    {
        \PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    public static function suite()
    {
        $suite = new \Doctrine\Tests\DbalTestSuite('Doctrine DBAL');

        // Platform tests
        $suite->addTestSuite('Doctrine\Tests\DBAL\Platforms\SqlitePlatformTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Platforms\MySqlPlatformTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Platforms\PostgreSqlPlatformTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Platforms\MsSqlPlatformTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Platforms\OraclePlatformTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Platforms\ReservedKeywordsValidatorTest');

        // Type tests
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\ArrayTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\ObjectTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\DateTimeTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\DateTimeTzTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\VarDateTimeTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\DateTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\TimeTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\BooleanTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\DecimalTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\IntegerTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\SmallIntTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\StringTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Types\FloatTest');

        // Schema tests
        $suite->addTestSuite('Doctrine\Tests\DBAL\Schema\ColumnTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Schema\IndexTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Schema\TableTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Schema\SchemaTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Schema\Visitor\SchemaSqlCollectorTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Schema\ComparatorTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Schema\SchemaDiffTest');

        // Driver manager test
        $suite->addTestSuite('Doctrine\Tests\DBAL\DriverManagerTest');

        // Connection test
        $suite->addTestSuite('Doctrine\Tests\DBAL\ConnectionTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\SQLParserUtilsTest');
        
        // Events and Listeners
        $suite->addTestSuite('Doctrine\Tests\DBAL\Events\OracleSessionInitTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Events\MysqlSessionInitTest');

        // All Functional DBAL tests
        $suite->addTest(Functional\AllTests::suite());

        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Dbal_Platforms_AllTests::main') {
    AllTests::main();
}

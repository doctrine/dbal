<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Tests\DBAL\Functional;
use Doctrine\Tests\TestUtil;

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Dbal_Functional_AllTests::main');
}

require_once __DIR__ . '/../../TestInit.php';

class AllTests
{
    public static function main()
    {
        \PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    public static function suite()
    {
        $suite = new \Doctrine\Tests\DbalFunctionalTestSuite('Doctrine Dbal Functional');

        $conn= TestUtil::getConnection();
        $sm = $conn->getSchemaManager();

        if ($sm instanceof \Doctrine\DBAL\Schema\SqliteSchemaManager) {
            $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\Schema\SqliteSchemaManagerTest');
        } else if ($sm instanceof \Doctrine\DBAL\Schema\MySqlSchemaManager) {
            $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\Schema\MySqlSchemaManagerTest');
        } else if ($sm instanceof \Doctrine\DBAL\Schema\PostgreSqlSchemaManager) {
            $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\Schema\PostgreSqlSchemaManagerTest');
        } else if ($sm instanceof \Doctrine\DBAL\Schema\OracleSchemaManager) {
            $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\Schema\OracleSchemaManagerTest');
        } else if ($sm instanceof \Doctrine\DBAL\Schema\DB2SchemaManager) {
            $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\Schema\Db2SchemaManagerTest');
        }
        $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\ConnectionTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\DataAccessTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\WriteTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\LoggingTest');
        $suite->addTestSuite('Doctrine\Tests\DBAL\Functional\TypeConversionTest');

        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Dbal_Functional_AllTests::main') {
    AllTests::main();
}

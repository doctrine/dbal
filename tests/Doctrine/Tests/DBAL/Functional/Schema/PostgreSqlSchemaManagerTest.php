<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

require_once __DIR__ . '/../../../TestInit.php';
 
class PostgreSqlSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    /**
     * @group DBAL-21
     */
    public function testSupportDomainTypeFallback()
    {
        $createDomainTypeSQL = "CREATE DOMAIN MyMoney AS DECIMAL(18,2)";
        $this->_conn->exec($createDomainTypeSQL);

        $createTableSQL = "CREATE TABLE domain_type_test (id INT PRIMARY KEY, value MyMoney)";
        $this->_conn->exec($createTableSQL);

        $table = $this->_conn->getSchemaManager()->listTableDetails('domain_type_test');
        $this->assertType('Doctrine\DBAL\Types\DecimalType', $table->getColumn('value')->getType());

        Type::addType('MyMoney', 'Doctrine\Tests\DBAL\Functional\Schema\MoneyType');
        $this->_conn->getDatabasePlatform()->registerDoctrineTypeMapping('MyMoney', 'MyMoney');

        $table = $this->_conn->getSchemaManager()->listTableDetails('domain_type_test');
        $this->assertType('Doctrine\Tests\DBAL\Functional\Schema\MoneyType', $table->getColumn('value')->getType());
    }
}

class MoneyType extends Type
{

    public function getName()
    {
        return "MyMoney";
    }

    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'MyMoney';
    }

}
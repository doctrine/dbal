<?php

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Sharding\SQLAzure\SQLAzureShardManager;
use PHPUnit\Framework\TestCase;
use function strpos;

abstract class AbstractTestCase extends TestCase
{
    /** @var Connection */
    protected $conn;

    /** @var SQLAzureShardManager */
    protected $sm;

    protected function setUp() : void
    {
        if (! isset($GLOBALS['db_type']) || strpos($GLOBALS['db_type'], 'sqlsrv') === false) {
            $this->markTestSkipped('No driver or sqlserver driver specified.');
        }

        $params     = [
            'driver' => $GLOBALS['db_type'],
            'dbname' => $GLOBALS['db_name'],
            'user' => $GLOBALS['db_username'],
            'password' => $GLOBALS['db_password'],
            'host' => $GLOBALS['db_host'],
            'sharding' => [
                'federationName' => 'Orders_Federation',
                'distributionKey' => 'CustID',
                'distributionType' => 'integer',
                'filteringEnabled' => false,
            ],
            'driverOptions' => ['MultipleActiveResultSets' => false],
        ];
        $this->conn = DriverManager::getConnection($params);

        $serverEdition = $this->conn->fetchColumn("SELECT CONVERT(NVARCHAR(128), SERVERPROPERTY('Edition'))");

        if (strpos($serverEdition, 'SQL Azure') !== 0) {
            $this->markTestSkipped('SQL Azure only test.');
        }

        // assume database is created and schema is:
        // Global products table
        // Customers, Orders, OrderItems federation tables.
        // See http://cloud.dzone.com/articles/using-sql-azure-federations
        $this->sm = new SQLAzureShardManager($this->conn);
    }

    protected function createShopSchema() : Schema
    {
        $schema = new Schema();

        $products = $schema->createTable('Products');
        $products->addColumn('ProductID', 'integer');
        $products->addColumn('SupplierID', 'integer');
        $products->addColumn('ProductName', 'string');
        $products->addColumn('Price', 'decimal', ['scale' => 2, 'precision' => 12]);
        $products->setPrimaryKey(['ProductID']);
        $products->addOption('azure.federated', true);

        $customers = $schema->createTable('Customers');
        $customers->addColumn('CustomerID', 'integer');
        $customers->addColumn('CompanyName', 'string');
        $customers->addColumn('FirstName', 'string');
        $customers->addColumn('LastName', 'string');
        $customers->setPrimaryKey(['CustomerID']);
        $customers->addOption('azure.federated', true);
        $customers->addOption('azure.federatedOnColumnName', 'CustomerID');

        $orders = $schema->createTable('Orders');
        $orders->addColumn('CustomerID', 'integer');
        $orders->addColumn('OrderID', 'integer');
        $orders->addColumn('OrderDate', 'datetime');
        $orders->setPrimaryKey(['CustomerID', 'OrderID']);
        $orders->addOption('azure.federated', true);
        $orders->addOption('azure.federatedOnColumnName', 'CustomerID');

        $orderItems = $schema->createTable('OrderItems');
        $orderItems->addColumn('CustomerID', 'integer');
        $orderItems->addColumn('OrderID', 'integer');
        $orderItems->addColumn('ProductID', 'integer');
        $orderItems->addColumn('Quantity', 'integer');
        $orderItems->setPrimaryKey(['CustomerID', 'OrderID', 'ProductID']);
        $orderItems->addOption('azure.federated', true);
        $orderItems->addOption('azure.federatedOnColumnName', 'CustomerID');

        return $schema;
    }
}

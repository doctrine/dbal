<?php

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Sharding\SQLAzure\SQLAzureShardManager;

abstract class AbstractTestCase extends \PHPUnit_Framework_TestCase
{
    protected $conn;
    protected $sm;

    public function setUp()
    {
        if (!isset($GLOBALS['db_type']) || strpos($GLOBALS['db_type'], "sqlsrv") === false) {
            $this->markTestSkipped('No driver or sqlserver driver specified.');
        }

        $params = array(
            'driver' => $GLOBALS['db_type'],
            'dbname' => $GLOBALS['db_name'],
            'user' => $GLOBALS['db_username'],
            'password' => $GLOBALS['db_password'],
            'host' => $GLOBALS['db_host'],
            'sharding' => array(
                'federationName' => 'Orders_Federation',
                'distributionKey' => 'CustID',
                'distributionType' => 'integer',
                'filteringEnabled' => false,
            ),
            'driverOptions' => array('MultipleActiveResultSets' => false)
        );
        $this->conn = DriverManager::getConnection($params);
        // assume database is created and schema is:
        // Global products table
        // Customers, Orders, OrderItems federation tables.
        // See http://cloud.dzone.com/articles/using-sql-azure-federations
        $this->sm = new SQLAzureShardManager($this->conn);
    }

    public function createShopSchema()
    {
        $schema = new Schema();

        $products = $schema->createTable('Products');
        $products->addColumn('ProductID', 'integer');
        $products->addColumn('SupplierID', 'integer');
        $products->addColumn('ProductName', 'string');
        $products->addColumn('Price', 'decimal', array('scale' => 2, 'precision' => 12));
        $products->setPrimaryKey(array('ProductID'));
        $products->addOption('azure.federated', true);

        $customers = $schema->createTable('Customers');
        $customers->addColumn('CustomerID', 'integer');
        $customers->addColumn('CompanyName', 'string');
        $customers->addColumn('FirstName', 'string');
        $customers->addColumn('LastName', 'string');
        $customers->setPrimaryKey(array('CustomerID'));
        $customers->addOption('azure.federated', true);
        $customers->addOption('azure.federatedOnColumnName', 'CustomerID');

        $orders = $schema->createTable('Orders');
        $orders->addColumn('CustomerID', 'integer');
        $orders->addColumn('OrderID', 'integer');
        $orders->addColumn('OrderDate', 'datetime');
        $orders->setPrimaryKey(array('CustomerID', 'OrderID'));
        $orders->addOption('azure.federated', true);
        $orders->addOption('azure.federatedOnColumnName', 'CustomerID');

        $orderItems = $schema->createTable('OrderItems');
        $orderItems->addColumn('CustomerID', 'integer');
        $orderItems->addColumn('OrderID', 'integer');
        $orderItems->addColumn('ProductID', 'integer');
        $orderItems->addColumn('Quantity', 'integer');
        $orderItems->setPrimaryKey(array('CustomerID', 'OrderID', 'ProductID'));
        $orderItems->addOption('azure.federated', true);
        $orderItems->addOption('azure.federatedOnColumnName', 'CustomerID');

        return $schema;
    }
}

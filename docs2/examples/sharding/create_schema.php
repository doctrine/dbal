<?php
// create_schema.php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Shards\DBAL\SQLAzure\SQLAzureSchemaSynchronizer;

require_once 'bootstrap.php';

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

// Create the Schema + Federation:
$synchronizer = new SQLAzureSchemaSynchronizer($conn, $shardManager);

// Or jut look at the SQL:
echo implode("\n", $synchronizer->getCreateSchema($schema));

$synchronizer->createSchema($schema);


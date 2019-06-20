<?php

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Sharding\SQLAzure\SQLAzureFederationsSynchronizer;

class SQLAzureFederationsSynchronizerTest extends AbstractTestCase
{
    public function testCreateSchema() : void
    {
        $schema = $this->createShopSchema();

        $synchronizer = new SQLAzureFederationsSynchronizer($this->conn, $this->sm);
        $sql          = $synchronizer->getCreateSchema($schema);

        self::assertEquals([
            "--Create Federation\nCREATE FEDERATION Orders_Federation (CustID INT  RANGE)",
            'USE FEDERATION Orders_Federation (CustID = 0) WITH RESET, FILTERING = OFF;',
            'CREATE TABLE Products (ProductID INT NOT NULL, SupplierID INT NOT NULL, ProductName NVARCHAR(255) NOT NULL, Price NUMERIC(12, 2) NOT NULL, PRIMARY KEY (ProductID))',
            'CREATE TABLE Customers (CustomerID INT NOT NULL, CompanyName NVARCHAR(255) NOT NULL, FirstName NVARCHAR(255) NOT NULL, LastName NVARCHAR(255) NOT NULL, PRIMARY KEY (CustomerID))',
            'CREATE TABLE Orders (CustomerID INT NOT NULL, OrderID INT NOT NULL, OrderDate DATETIME2(6) NOT NULL, PRIMARY KEY (CustomerID, OrderID))',
            'CREATE TABLE OrderItems (CustomerID INT NOT NULL, OrderID INT NOT NULL, ProductID INT NOT NULL, Quantity INT NOT NULL, PRIMARY KEY (CustomerID, OrderID, ProductID))',
        ], $sql);
    }

    public function testUpdateSchema() : void
    {
        $schema = $this->createShopSchema();

        $synchronizer = new SQLAzureFederationsSynchronizer($this->conn, $this->sm);
        $synchronizer->dropAllSchema();

        $sql = $synchronizer->getUpdateSchema($schema);

        self::assertEquals([], $sql);
    }

    public function testDropSchema() : void
    {
        $schema = $this->createShopSchema();

        $synchronizer = new SQLAzureFederationsSynchronizer($this->conn, $this->sm);
        $synchronizer->dropAllSchema();
        $synchronizer->createSchema($schema);
        $sql = $synchronizer->getDropSchema($schema);

        self::assertCount(5, $sql);
    }
}

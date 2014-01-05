<?php
namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Sharding\SQLAzure\SQLAzureFederationsSynchronizer;

class FunctionalTest extends AbstractTestCase
{
    public function testSharding()
    {
        $schema = $this->createShopSchema();

        $synchronizer = new SQLAzureFederationsSynchronizer($this->conn, $this->sm);
        $synchronizer->dropAllSchema();
        $synchronizer->createSchema($schema);

        $this->sm->selectShard(0);

        $this->conn->insert("Products", array(
            "ProductID" => 1,
            "SupplierID" => 2,
            "ProductName" => "Test",
            "Price" => 10.45
        ));

        $this->conn->insert("Customers", array(
            "CustomerID" => 1,
            "CompanyName" => "Foo",
            "FirstName" => "Benjamin",
            "LastName" => "E.",
        ));

        $query = "SELECT * FROM Products";
        $data = $this->conn->fetchAll($query);
        $this->assertTrue(count($data) > 0);

        $query = "SELECT * FROM Customers";
        $data = $this->conn->fetchAll($query);
        $this->assertTrue(count($data) > 0);

        $data = $this->sm->queryAll("SELECT * FROM Customers");
        $this->assertTrue(count($data) > 0);
    }
}


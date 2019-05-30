<?php

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Sharding\SQLAzure\SQLAzureFederationsSynchronizer;
use function count;

class FunctionalTest extends AbstractTestCase
{
    public function testSharding() : void
    {
        $schema = $this->createShopSchema();

        $synchronizer = new SQLAzureFederationsSynchronizer($this->conn, $this->sm);
        $synchronizer->dropAllSchema();
        $synchronizer->createSchema($schema);

        $this->sm->selectShard(0);

        $this->conn->insert('Products', [
            'ProductID' => 1,
            'SupplierID' => 2,
            'ProductName' => 'Test',
            'Price' => 10.45,
        ]);

        $this->conn->insert('Customers', [
            'CustomerID' => 1,
            'CompanyName' => 'Foo',
            'FirstName' => 'Benjamin',
            'LastName' => 'E.',
        ]);

        $query = 'SELECT * FROM Products';
        $data  = $this->conn->fetchAll($query);
        self::assertGreaterThan(0, count($data));

        $query = 'SELECT * FROM Customers';
        $data  = $this->conn->fetchAll($query);
        self::assertGreaterThan(0, count($data));

        $data = $this->sm->queryAll('SELECT * FROM Customers');
        self::assertGreaterThan(0, count($data));
    }
}

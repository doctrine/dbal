SQLAzure Sharding Tutorial
==========================

.. note::

    The sharding extension is currently in transition from a seperate Project
    into DBAL. Class names may differ.

This tutorial builds upon the `Brian Swans tutorial
<http://blogs.msdn.com/b/silverlining/archive/2012/01/18/using-sql-azure-federations-via-php.aspx>`_
on SQLAzure Sharding and turns all the examples into examples using the Doctrine Sharding support.

It introduces SQL Azure Sharding, which is an abstraction layer in SQL Azure to
support sharding. Many features for sharding are implemented on the database
level, which makes it much easier to work with than generic sharding
implementations.

For this tutorial you need an Azure account. You don't need to deploy the code
on Azure, you can run it from your own machine against the remote database.

.. note::

    You can look at the code from the 'examples/sharding' directory.

Install Doctrine
----------------

For this tutorial we will install Doctrine and the Sharding Extension through
`Composer <http://getcomposer.org>`_ which is the easiest way to install
Doctrine. Composer is a new package manager for PHP. Download the
``composer.phar`` from their website and put it into a newly created folder for
this tutorial. Now create a ``composer.json`` file in this project root with
the following content:

    {
        "require": {
            "doctrine/dbal": "2.2.2",
            "doctrine/shards": "0.2"
        }
    }

Open up the commandline and switch to your tutorial root directory, then call
``php composer.phar install``. It will grab the code and install it into the
``vendor`` subdirectory of your project. It also creates an autoloader, so that
we don't have to care about this.

Setup Connection
----------------

The first thing to start with is setting up Doctrine and the database connection:

.. code-block:: php

    <?php
    // bootstrap.php
    use Doctrine\DBAL\DriverManager;
    use Doctrine\Shards\DBAL\SQLAzure\SQLAzureShardManager;

    require_once "vendor/autoload.php";

    $conn = DriverManager::getConnection(array(
        'driver'   => 'pdo_sqlsrv',
        'dbname'   => 'SalesDB',
        'host'     => 'tcp:dbname.windows.net',
        'user'     => 'user@dbname',
        'password' => 'XXX',
        'platform'       => new \Doctrine\DBAL\Platforms\SQLAzurePlatform(),
        'driverOptions'  => array('MultipleActiveResultSets' => false),
        'sharding' => array(
            'federationName'   => 'Orders_Federation',
            'distributionKey'  => 'CustId',
            'distributionType' => 'integer',
        )
    ));

    $shardManager = new SQLAzureShardManager($conn);

Create Database
---------------

Create a new database using the Azure/SQL Azure management console.

Create Schema
-------------

Doctrine has a powerful schema API. We don't need to use low-level DDL
statements to generate the database schema. Instead you can use an Object-Oriented API
to create the database schema and then have Doctrine turn it into DDL
statements.

We will recreate Brians example schema with Doctrine DBAL. Instead of having to
create federations and schema seperately as in his example, Doctrine will do it
all in one step:

.. code-block:: php

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
    $synchronizer->createSchema($schema);

    // Or jut look at the SQL:
    echo implode("\n", $synchronizer->getCreateSchema($schema));

View Federation Members
-----------------------

To see how many shard instances (called Federation Members) your SQLAzure database currently has
you can ask the ``ShardManager`` to enumerate all shards:

.. code-block:: php

    <?php
    // view_federation_members.php
    require_once "bootstrap.php";

    $shards = $shardManager->getShards();
    foreach ($shards as $shard) {
        print_r($shard);
    }

Insert Data
-----------

Now we want to insert some test data into the database to see the behavior when
we split the shards. We use the same test data as Brian, but use the Doctrine
API to insert them. To insert data into federated tables we have to select the
shard we want to put the data into. We can use the ShardManager to execute this
operation for us:

.. code-block:: php

    <?php
    // insert_data.php
    require_once "bootstrap.php";

    $shardManager->selectShard(0);

    $conn->insert("Products", array(
        "ProductID" => 386,
        "SupplierID" => 1001,
        "ProductName" => 'Titanium Extension Bracket Left Hand',
        "Price" => 5.25,
    ));
    $conn->insert("Products", array(
        "ProductID" => 387,
        "SupplierID" => 1001,
        "ProductName" => 'Titanium Extension Bracket Right Hand',
        "Price" => 5.25,
    ));
    $conn->insert("Products", array(
        "ProductID" => 388,
        "SupplierID" => 1001,
        "ProductName" => 'Fusion Generator Module 5 kV',
        "Price" => 10.50,
    ));
    $conn->insert("Products", array(
        "ProductID" => 388,
        "SupplierID" => 1001,
        "ProductName" => 'Bypass Filter 400 MHz Low Pass',
        "Price" => 10.50,
    ));

    $conn->insert("Customers", array(
        'CustomerID' => 10,
        'CompanyName' => 'Van Nuys',
        'FirstName' => 'Catherine',
        'LastName' => 'Abel',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 20,
        'CompanyName' => 'Abercrombie',
        'FirstName' => 'Kim',
        'LastName' => 'Branch',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 30,
        'CompanyName' => 'Contoso',
        'FirstName' => 'Frances',
        'LastName' => 'Adams',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 40,
        'CompanyName' => 'A. Datum Corporation',
        'FirstName' => 'Mark',
        'LastName' => 'Harrington',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 50,
        'CompanyName' => 'Adventure Works',
        'FirstName' => 'Keith',
        'LastName' => 'Harris',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 60,
        'CompanyName' => 'Alpine Ski House',
        'FirstName' => 'Wilson',
        'LastName' => 'Pais',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 70,
        'CompanyName' => 'Baldwin Museum of Science',
        'FirstName' => 'Roger',
        'LastName' => 'Harui',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 80,
        'CompanyName' => 'Blue Yonder Airlines',
        'FirstName' => 'Pilar',
        'LastName' => 'Pinilla',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 90,
        'CompanyName' => 'City Power & Light',
        'FirstName' => 'Kari',
        'LastName' => 'Hensien',
    ));
    $conn->insert("Customers", array(
        'CustomerID' => 100,
        'CompanyName' => 'Coho Winery',
        'FirstName' => 'Peter',
        'LastName' => 'Brehm',
    ));

    $conn->executeUpdate("DECLARE @orderId INT

        DECLARE @customerId INT

        SET @orderId = 10
        SELECT @customerId = CustomerId FROM Customers WHERE LastName = 'Hensien' and FirstName = 'Kari'

        INSERT INTO Orders (CustomerId, OrderId, OrderDate)
        VALUES (@customerId, @orderId, GetDate())

        INSERT INTO OrderItems (CustomerID, OrderID, ProductID, Quantity)
        VALUES (@customerId, @orderId, 388, 4)

        SET @orderId = 20
        SELECT @customerId = CustomerId FROM Customers WHERE LastName = 'Harui' and FirstName = 'Roger'

        INSERT INTO Orders (CustomerId, OrderId, OrderDate)
        VALUES (@customerId, @orderId, GetDate())

        INSERT INTO OrderItems (CustomerID, OrderID, ProductID, Quantity)
        VALUES (@customerId, @orderId, 389, 2)

        SET @orderId = 30
        SELECT @customerId = CustomerId FROM Customers WHERE LastName = 'Brehm' and FirstName = 'Peter'

        INSERT INTO Orders (CustomerId, OrderId, OrderDate)
        VALUES (@customerId, @orderId, GetDate())

        INSERT INTO OrderItems (CustomerID, OrderID, ProductID, Quantity)
        VALUES (@customerId, @orderId, 387, 3)

        SET @orderId = 40
        SELECT @customerId = CustomerId FROM Customers WHERE LastName = 'Pais' and FirstName = 'Wilson'

        INSERT INTO Orders (CustomerId, OrderId, OrderDate)
        VALUES (@customerId, @orderId, GetDate())

        INSERT INTO OrderItems (CustomerID, OrderID, ProductID, Quantity)
        VALUES (@customerId, @orderId, 388, 1)"
    );

This puts the data into the currently only existing federation member. We
selected that federation member by picking 0 as distribution value, which is by
definition part of the only existing federation.

Split Federation
----------------

Now lets split the federation, creating a second federation member. SQL Azure
will automatically redistribute the data into the two federations after you
executed this command.

.. code-block:: php

    <?php
    // split_federation.php
    require_once 'bootstrap.php';

    $shardManager->splitFederation(60);

This little script uses the shard manager with a special method only existing
on the SQL AZure implementation ``splitFederation``. It accepts a value at
at which the split is executed.

If you reexecute the ``view_federation_members.php`` script you can now see
that there are two federation members instead of just one as before. You can
see with the ``rangeLow`` and ``rangeHigh`` parameters what customers and
related entries are now served by which federation.

Inserting Data after Split
--------------------------

Now after we splitted the data we now have to make sure to be connected to the
right federation before inserting data. Lets add a new customer with ID 55 and
have him create an order.

.. code-block:: php

    <?php
    // insert_data_aftersplit.php
    require_once 'bootstrap.php';

    $newCustomerId = 55;

    $shardManager->selectShard($newCustomerId);

    $conn->insert("Customers", array(
        "CustomerID" => $newCustomerId,
        "CompanyName" => "Microsoft",
        "FirstName" => "Brian",
        "LastName" => "Swan",
    ));

    $conn->insert("Orders", array(
        "CustomerID" => 55,
        "OrderID" => 37,
        "OrderDate" => date('Y-m-d H:i:s'),
    ));

    $conn->insert("OrderItems", array(
        "CustomerID" => 55,
        "OrderID" => 37,
        "ProductID" => 387,
        "Quantity" => 1,
    ));

As you can see its very important to pick the right distribution key in your
sharded application. Otherwise you have to switch the shards very often, which
is not really easy to work with. If you pick the sharding key right then it
should be possible to select the shard only once per request for the major
number of use-cases.

Fan-out the queries accross multiple shards should only be necessary for a
small number of queries, because these kind of queries are complex.

Querying data with filtering off
--------------------------------

To access the data you have to pick a shard again and then start selecting data
from it.

.. code-block:: php

    <?php
    // query_filtering_off.php
    require_once "bootstrap.php";

    $shardManager->selectShard(0);

    $data = $conn->fetchAll('SELECT * FROM Customers');
    print_r($data);

This returns all customers from the shard with distribution value 0. This will
be all customers with id 10 to less than 60, since we split federations at 60.

Querying data with filtering on
-------------------------------

One special feature of SQL Azure is the possibility to database level filtering
based on the sharding distribution values. This means that SQL Azure will add
WHERE clauses with distributionkey=current distribution value conditions to
each distribution key.

.. code-block:: php

    <?php
    // query_filtering_on.php
    require_once "bootstrap.php";

    $shardManager->setFilteringEnabled(true);
    $shardManager->selectShard(55);

    $data = $conn->fetchAll('SELECT * FROM Customers');
    print_r($data);

Now you only get the customer with id = 55. The same holds for queries on the
``Orders`` and ``OrderItems`` table, which are restricted by customer id = 55.


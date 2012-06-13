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
    "ProductID" => 389,
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

$conn->executeUpdate("
    DECLARE @orderId INT

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
    VALUES (@customerId, @orderId, 388, 1)");

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

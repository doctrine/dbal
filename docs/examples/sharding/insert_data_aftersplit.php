<?php
// insert_data_aftersplit.php
require_once 'bootstrap.php';

$newCustomerId = 55;

$shardManager->selectShard($newCustomerId);

$conn->insert("Customers", [
    "CustomerID" => $newCustomerId,
    "CompanyName" => "Microsoft",
    "FirstName" => "Brian",
    "LastName" => "Swan",
]);

$conn->insert("Orders", [
    "CustomerID" => 55,
    "OrderID" => 37,
    "OrderDate" => date('Y-m-d H:i:s'),
]);

$conn->insert("OrderItems", [
    "CustomerID" => 55,
    "OrderID" => 37,
    "ProductID" => 387,
    "Quantity" => 1,
]);

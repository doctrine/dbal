<?php
// bootstrap.php
use Doctrine\DBAL\DriverManager;
use Doctrine\Shards\DBAL\SQLAzure\SQLAzureShardManager;

require_once "vendor/autoload.php";

$config = array(
    'dbname'   => 'SalesDB',
    'host'     => 'tcp:dbname.windows.net',
    'user'     => 'user@dbname',
    'password' => 'XXX',
    'sharding' => array(
        'federationName'   => 'Orders_Federation',
        'distributionKey'  => 'CustId',
        'distributionType' => 'integer',
    )
);

if ($config['host'] == "tcp:dbname.windows.net") {
    die("You have to change the configuration to your Azure account.\n");
}

$conn = DriverManager::getConnection($config);
$shardManager = new SQLAzureShardManager($conn);


<?php

declare(strict_types=1);

namespace Doctrine\StaticAnalysis\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Exception;

$smf = new DefaultSchemaManagerFactory();
$schemaManager = $smf->createSchemaManager(new Connection([], new Driver()));

if (!$schemaManager instanceof PostgreSQLSchemaManager) {
    throw new Exception('should not happen');
}

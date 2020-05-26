<?php

namespace Doctrine\DBAL\Tests\Functional\Connection\BackwardCompatibility;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tests\Functional\Connection\FetchTest as BaseFetchTest;
use function array_merge;

class FetchTest extends BaseFetchTest
{
    public function setUp() : void
    {
        parent::setUp();

        $this->connection = DriverManager::getConnection(
            array_merge($this->connection->getParams(), [
                'wrapperClass' => Connection::class,
            ]),
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );
    }
}

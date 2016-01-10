<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;

interface ServerInfoAwareConnectionMock extends Connection, ServerInfoAwareConnection
{
}

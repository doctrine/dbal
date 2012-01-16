<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema;

class SQLServerSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
	protected function getPlatformName()
	{
		return "mssql";
	}
}

<?php

namespace Doctrine\Tests\DBAL\Mocks;

use Doctrine\DBAL\TransactionManager;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * The transaction manager mock is actually generated dynamically by PHPUnit,
 * but this class allows type-hinting both TransactionManager and MockObject at the same time
 * for IDE code inspection and completion to work properly.
 */
abstract class TransactionManagerMock extends TransactionManager implements MockObject
{
}

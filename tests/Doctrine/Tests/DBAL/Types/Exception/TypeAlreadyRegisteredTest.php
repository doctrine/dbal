<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Types\Exception;

use Doctrine\DBAL\Types\Exception\TypeAlreadyRegistered;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use function preg_match;

class TypeAlreadyRegisteredTest extends TestCase
{
    public function testNew() : void
    {
        $exception = TypeAlreadyRegistered::new(Type::getType('string'));

        self::assertInstanceOf(TypesException::class, $exception);
        self::assertTrue(preg_match('/Type of the class Doctrine\\\DBAL\\\Types\\\StringType@([0-9a-zA-Z]+) is already registered./', $exception->getMessage()) === 1);
    }
}

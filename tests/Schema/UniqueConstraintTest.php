<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

class UniqueConstraintTest extends TestCase
{
    use VerifyDeprecations;

    /** @throws Exception */
    public function testGetNonNullObjectName(): void
    {
        $uniqueConstraint = new UniqueConstraint('uq_user_id', ['user_id']);
        $name             = $uniqueConstraint->getObjectName();

        self::assertNotNull($name);
        self::assertEquals(Identifier::unquoted('uq_user_id'), $name->getIdentifier());
    }

    /** @throws Exception */
    public function testGetNullObjectName(): void
    {
        $uniqueConstraint = new UniqueConstraint('', ['user_id']);

        self::assertNull($uniqueConstraint->getObjectName());
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use PHPUnit\Framework\TestCase;

class SequenceTest extends TestCase
{
    public function testEmptyName(): void
    {
        $this->expectException(InvalidName::class);

        new Sequence('');
    }

    public function testOverqualifiedName(): void
    {
        $this->expectException(InvalidName::class);

        new Sequence('identity.auth.user_id_seq');
    }

    /** @throws Exception */
    public function testGetUnqualifiedObjectName(): void
    {
        $sequence = new Sequence('user_id_seq');
        $name     = $sequence->getObjectName();

        self::assertEquals(Identifier::unquoted('user_id_seq'), $name->getUnqualifiedName());
        self::assertNull($name->getQualifier());
    }

    /** @throws Exception */
    public function testGetQualifiedObjectName(): void
    {
        $sequence = new Sequence('auth.user_id_seq');
        $name     = $sequence->getObjectName();

        self::assertEquals(Identifier::unquoted('user_id_seq'), $name->getUnqualifiedName());
        self::assertEquals(Identifier::unquoted('auth'), $name->getQualifier());
    }
}

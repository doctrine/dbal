<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\AbstractNamedObject;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

class AbstractNamedObjectTest extends TestCase
{
    use VerifyDeprecations;

    public function testEmptyName(): void
    {
        $this->expectException(InvalidName::class);

        // @phpstan-ignore expr.resultUnused
        new /** @extends AbstractNamedObject<Name> */
        class ('') extends AbstractNamedObject {
        };
    }
}

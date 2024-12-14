<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Identifier;
use PHPUnit\Framework\TestCase;

class AbstractAssetTest extends TestCase
{
    public function testInvalidName(): void
    {
        $this->expectException(InvalidName::class);
        new Identifier(' ');
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Driver\Encodings;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\VarCharType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VarCharTest extends TestCase
{
    /** @var AbstractPlatform|MockObject */
    private $platform;

    /** @var VarCharType */
    private $type;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->type = new VarCharType();
    }

    public function testReturnsBindingType(): void
    {
        self::assertEquals($this->type->getBindingType(), ParameterType::STRING | Encodings::ASCII);
    }

    public function testReturnsSQLDeclaration(): void
    {
        self::assertEquals('VARCHAR(12)', $this->type->getSQLDeclaration(['length' => 12], $this->platform));
        self::assertEquals('CHAR(12)', $this->type->getSQLDeclaration(['length' => 12, 'fixed' => true], $this->platform));
    }
}

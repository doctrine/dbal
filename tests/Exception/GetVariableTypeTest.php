<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Exception;

use Doctrine\DBAL\Exception\GetVariableType;
use PHPUnit\Framework\TestCase;
use stdClass;

use function tmpfile;

class GetVariableTypeTest extends TestCase
{
    /**
     * @param mixed $value
     *
     * @dataProvider provideDataForFormatVariable
     */
    public function testFormatVariable(string $expected, $value): void
    {
        self::assertSame($expected, (new GetVariableType())->__invoke($value));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function provideDataForFormatVariable(): array
    {
        return [
            ['string', ''],
            ['string', 'test'],
            ['double', 1.0],
            ['integer', 1],
            ['NULL', null],
            ['stdClass', new stdClass()],
            ['stream', tmpfile()],
            ['true', true],
            ['false', false],
            ['array', [true, 1, 2, 3, 'test']],
        ];
    }
}

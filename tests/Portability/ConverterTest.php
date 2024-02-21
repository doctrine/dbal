<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Portability;

use Doctrine\DBAL\Portability\Converter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    /**
     * @param list<mixed>|false $row
     * @param list<mixed>|false $expected
     */
    #[DataProvider('convertNumericProvider')]
    public function testConvertNumeric(
        array|false $row,
        bool $convertEmptyStringToNull,
        bool $rightTrimString,
        array|false $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->createConverter($convertEmptyStringToNull, $rightTrimString, null)
                ->convertNumeric($row),
        );
    }

    /** @return iterable<string,array<int,mixed>> */
    public static function convertNumericProvider(): iterable
    {
        $row = ['X ', ''];

        yield 'None' => [
            $row,
            false,
            false,
            ['X ', ''],
        ];

        yield 'Trim' => [
            $row,
            false,
            true,
            ['X', ''],
        ];

        yield 'Empty to NULL' => [
            $row,
            true,
            false,
            ['X ', null],
        ];

        yield 'Empty to NULL and Trim' => [
            $row,
            true,
            true,
            ['X', null],
        ];

        yield 'False' => [false, true, true, false];
    }

    /**
     * @param array<string,mixed>|false                        $row
     * @param Converter::CASE_LOWER|Converter::CASE_UPPER|null $case
     * @param array<string,mixed>|false                        $expected
     */
    #[DataProvider('convertAssociativeProvider')]
    public function testConvertAssociative(
        array|false $row,
        bool $convertEmptyStringToNull,
        bool $rightTrimString,
        ?int $case,
        array|false $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->createConverter($convertEmptyStringToNull, $rightTrimString, $case)
                ->convertAssociative($row),
        );
    }

    /** @return iterable<string,array<int,mixed>> */
    public static function convertAssociativeProvider(): iterable
    {
        $row = [
            'FOO' => '',
            'BAR' => 'X ',
        ];

        yield 'None' => [
            $row,
            false,
            false,
            null,
            [
                'FOO' => '',
                'BAR' => 'X ',
            ],
        ];

        yield 'Trim' => [
            $row,
            false,
            true,
            null,
            [
                'FOO' => '',
                'BAR' => 'X',
            ],
        ];

        yield 'Empty to NULL' => [
            $row,
            true,
            false,
            null,
            [
                'FOO' => null,
                'BAR' => 'X ',
            ],
        ];

        yield 'Empty to NULL and Trim' => [
            $row,
            true,
            true,
            null,
            [
                'FOO' => null,
                'BAR' => 'X',
            ],
        ];

        yield 'To lower' => [
            $row,
            false,
            false,
            Converter::CASE_LOWER,
            [
                'foo' => '',
                'bar' => 'X ',
            ],
        ];

        yield 'Trim and to lower' => [
            $row,
            false,
            true,
            Converter::CASE_LOWER,
            [
                'foo' => '',
                'bar' => 'X',
            ],
        ];

        yield 'Empty to NULL and to lower' => [
            $row,
            true,
            false,
            Converter::CASE_LOWER,
            [
                'foo' => null,
                'bar' => 'X ',
            ],
        ];

        yield 'Trim, empty to NULL and to lower' => [
            $row,
            true,
            true,
            Converter::CASE_LOWER,
            [
                'foo' => null,
                'bar' => 'X',
            ],
        ];

        yield 'False' => [false, true, true, null, false];
    }

    #[DataProvider('convertOneProvider')]
    public function testConvertOne(
        mixed $value,
        bool $convertEmptyStringToNull,
        bool $rightTrimString,
        mixed $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->createConverter($convertEmptyStringToNull, $rightTrimString, null)
                ->convertOne($value),
        );
    }

    /** @return iterable<string,array<int,mixed>> */
    public static function convertOneProvider(): iterable
    {
        yield 'None, trailing space' => ['X ', false, false, 'X '];
        yield 'None, empty string' => ['', false, false, ''];
        yield 'Trim, trailing space' => ['X ', false, true, 'X'];
        yield 'Trim, empty string' => ['', false, true, ''];
        yield 'Empty to NULL, trailing space' => ['X ', true, false, 'X '];
        yield 'Empty to NULL, empty string' => ['', true, false, null];
        yield 'Empty to NULL and Trim, trailing space' => ['X ', true, true, 'X'];
        yield 'Empty to NULL and Trim, empty string' => ['', true, true, null];

        yield 'False' => [false, true, true, false];
    }

    /**
     * @param list<list<mixed>> $data
     * @param list<list<mixed>> $expected
     */
    #[DataProvider('convertAllNumericProvider')]
    public function testConvertAllNumeric(
        array $data,
        bool $convertEmptyStringToNull,
        bool $rightTrimString,
        array $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->createConverter($convertEmptyStringToNull, $rightTrimString, null)
                ->convertAllNumeric($data),
        );
    }

    /** @return iterable<string,array<int,mixed>> */
    public static function convertAllNumericProvider(): iterable
    {
        $data = [
            ['X ', ''],
            ['', 'Y '],
        ];

        yield 'None' => [
            $data,
            false,
            false,
            [
                ['X ', ''],
                ['', 'Y '],
            ],
        ];

        yield 'Trim' => [
            $data,
            false,
            true,
            [
                ['X', ''],
                ['', 'Y'],
            ],
        ];

        yield 'Empty to NULL' => [
            $data,
            true,
            false, [
                ['X ', null],
                [null, 'Y '],
            ],
        ];

        yield 'Empty to NULL and Trim' => [
            $data,
            true,
            true, [
                ['X', null],
                [null, 'Y'],
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>>                        $row
     * @param Converter::CASE_LOWER|Converter::CASE_UPPER|null $case
     * @param list<array<string,mixed>>                        $expected
     */
    #[DataProvider('convertAllAssociativeProvider')]
    public function testConvertAllAssociative(
        array $row,
        bool $convertEmptyStringToNull,
        bool $rightTrimString,
        ?int $case,
        array $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->createConverter($convertEmptyStringToNull, $rightTrimString, $case)
                ->convertAllAssociative($row),
        );
    }

    /** @return iterable<string,array<int,mixed>> */
    public static function convertAllAssociativeProvider(): iterable
    {
        $data = [
            [
                'FOO' => 'X ',
                'BAR' => '',
            ],
            [
                'FOO' => '',
                'BAR' => 'Y ',
            ],
        ];

        yield 'None' => [
            $data,
            false,
            false,
            null,
            [
                [
                    'FOO' => 'X ',
                    'BAR' => '',
                ],
                [
                    'FOO' => '',
                    'BAR' => 'Y ',
                ],
            ],
        ];

        yield 'Trim' => [
            $data,
            false,
            true,
            null,
            [
                [
                    'FOO' => 'X',
                    'BAR' => '',
                ],
                [
                    'FOO' => '',
                    'BAR' => 'Y',
                ],
            ],
        ];

        yield 'Empty to NULL' => [
            $data,
            true,
            false,
            null,
            [
                [
                    'FOO' => 'X ',
                    'BAR' => null,
                ],
                [
                    'FOO' => null,
                    'BAR' => 'Y ',
                ],
            ],
        ];

        yield 'Empty to NULL and Trim' => [
            $data,
            true,
            true,
            null,
            [
                [
                    'FOO' => 'X',
                    'BAR' => null,
                ],
                [
                    'FOO' => null,
                    'BAR' => 'Y',
                ],
            ],
        ];

        yield 'To lower' => [
            $data,
            false,
            false,
            Converter::CASE_LOWER,
            [
                [
                    'foo' => 'X ',
                    'bar' => '',
                ],
                [
                    'foo' => '',
                    'bar' => 'Y ',
                ],
            ],
        ];

        yield 'Trim and to lower' => [
            $data,
            false,
            true,
            Converter::CASE_LOWER,
            [
                [
                    'foo' => 'X',
                    'bar' => '',
                ],
                [
                    'foo' => '',
                    'bar' => 'Y',
                ],
            ],
        ];

        yield 'Empty to NULL and to lower' => [
            $data,
            true,
            false,
            Converter::CASE_LOWER,
            [
                [
                    'foo' => 'X ',
                    'bar' => null,
                ],
                [
                    'foo' => null,
                    'bar' => 'Y ',
                ],
            ],
        ];

        yield 'Trim, empty to NULL and to lower' => [
            $data,
            true,
            true,
            Converter::CASE_LOWER,
            [
                [
                    'foo' => 'X',
                    'bar' => null,
                ],
                [
                    'foo' => null,
                    'bar' => 'Y',
                ],
            ],
        ];
    }

    /**
     * @param list<mixed> $column
     * @param list<mixed> $expected
     */
    #[DataProvider('convertFirstColumnProvider')]
    public function testConvertFirstColumn(
        array $column,
        bool $convertEmptyStringToNull,
        bool $rightTrimString,
        array $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->createConverter($convertEmptyStringToNull, $rightTrimString, null)
                ->convertFirstColumn($column),
        );
    }

    /** @return iterable<string,array<int,mixed>> */
    public static function convertFirstColumnProvider(): iterable
    {
        $column = ['X ', ''];

        yield 'None' => [
            $column,
            false,
            false,
            ['X ', ''],
        ];

        yield 'Trim' => [
            $column,
            false,
            true,
            ['X', ''],
        ];

        yield 'Empty to NULL' => [
            $column,
            true,
            false,
            ['X ', null],
        ];

        yield 'Empty to NULL and Trim' => [
            $column,
            true,
            true,
            ['X', null],
        ];
    }

    /** @param Converter::CASE_LOWER|Converter::CASE_UPPER|null $case */
    private function createConverter(bool $convertEmptyStringToNull, bool $rightTrimString, ?int $case): Converter
    {
        return new Converter($convertEmptyStringToNull, $rightTrimString, $case);
    }
}

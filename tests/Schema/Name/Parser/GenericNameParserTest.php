<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Name\Parser;

use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\Parser\Exception;
use Doctrine\DBAL\Schema\Name\Parser\Exception\ExpectedDot;
use Doctrine\DBAL\Schema\Name\Parser\Exception\ExpectedNextIdentifier;
use Doctrine\DBAL\Schema\Name\Parser\Exception\UnableToParseIdentifier;
use Doctrine\DBAL\Schema\Name\Parser\GenericNameParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GenericNameParserTest extends TestCase
{
    private GenericNameParser $parser;

    protected function setUp(): void
    {
        $this->parser = new GenericNameParser();
    }

    /**
     * @param list<Identifier> $expected
     *
     * @throws Exception
     */
    #[DataProvider('validInputProvider')]
    public function testValidInput(string $input, array $expected): void
    {
        $name = $this->parser->parse($input);
        self::assertEquals($expected, $name->getIdentifiers());
    }

    /** @return iterable<array{string, list<Identifier>}> */
    public static function validInputProvider(): iterable
    {
        yield ['table', [Identifier::unquoted('table')]];
        yield ['schema.table', [Identifier::unquoted('schema'), Identifier::unquoted('table')]];

        yield ['"example.com"', [Identifier::quoted('example.com')]];
        yield ['`example.com`', [Identifier::quoted('example.com')]];
        yield ['[example.com]', [Identifier::quoted('example.com')]];

        yield [
            'a."b".c.`d`.e.[f].g',
            [
                Identifier::unquoted('a'),
                Identifier::quoted('b'),
                Identifier::unquoted('c'),
                Identifier::quoted('d'),
                Identifier::unquoted('e'),
                Identifier::quoted('f'),
                Identifier::unquoted('g'),
            ],
        ];

        yield ['"schema"."table"', [Identifier::quoted('schema'), Identifier::quoted('table')]];
        yield ['`schema`.`table`', [Identifier::quoted('schema'), Identifier::quoted('table')]];
        yield ['[schema].[table]', [Identifier::quoted('schema'), Identifier::quoted('table')]];

        yield [
            'schema."example.com"',
            [
                Identifier::unquoted('schema'),
                Identifier::quoted('example.com'),
            ],
        ];

        yield [
            '"a""b".`c``d`.[e]]f]',
            [
                Identifier::quoted('a"b'),
                Identifier::quoted('c`d'),
                Identifier::quoted('e]f'),
            ],
        ];

        yield [
            'schéma."übermäßigkeit".`àçcênt`.[éxtrême].çhâràctér',
            [
                Identifier::unquoted('schéma'),
                Identifier::quoted('übermäßigkeit'),
                Identifier::quoted('àçcênt'),
                Identifier::quoted('éxtrême'),
                Identifier::unquoted('çhâràctér'),
            ],
        ];

        yield [
            '" spaced identifier ".more',
            [
                Identifier::quoted(' spaced identifier '),
                Identifier::unquoted('more'),
            ],
        ];

        yield [
            '0."0".`0`.[0]',
            [
                Identifier::unquoted('0'),
                Identifier::quoted('0'),
                Identifier::quoted('0'),
                Identifier::quoted('0'),
            ],
        ];
    }

    /**
     * @param class-string<Exception> $expectedException
     *
     * @throws Exception
     */
    #[DataProvider('invalidInputProvider')]
    public function testInvalidInput(string $input, string $expectedException): void
    {
        $this->expectException($expectedException);
        $this->parser->parse($input);
    }

    /** @return iterable<array{string, class-string<Exception>}> */
    public static function invalidInputProvider(): iterable
    {
        yield ['', ExpectedNextIdentifier::class];

        yield ['"example.com', UnableToParseIdentifier::class];
        yield ['`example.com', UnableToParseIdentifier::class];
        yield ['[example.com', UnableToParseIdentifier::class];
        yield ['schema."example.com', UnableToParseIdentifier::class];
        yield ['schema.[example.com', UnableToParseIdentifier::class];
        yield ['schema.`example.com', UnableToParseIdentifier::class];

        yield ['schema.', ExpectedNextIdentifier::class];
        yield ['schema..', UnableToParseIdentifier::class];
        yield ['.table', UnableToParseIdentifier::class];

        yield ['schema.table name', ExpectedDot::class];
        yield ['"schema.[example.com]', UnableToParseIdentifier::class];
    }
}

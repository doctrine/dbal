<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function addcslashes;
use function array_map;
use function implode;
use function json_encode;
use function sprintf;
use function str_repeat;
use function strlen;

use const JSON_THROW_ON_ERROR;

final class ArraysOverlapExpressionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        try {
            $this->connection->getDatabasePlatform()->getArraysOverlapExpression('a', 'b');
        } catch (NotSupported) {
            self::markTestSkipped('The platform does not support testing whether arrays overlap.');
        }
    }

    /**
     * @param ?list<?string> $array1
     * @param ?list<?string> $array2
     */
    #[DataProvider('overlapProvider')]
    public function testOverlap(?array $array1, ?array $array2, ?bool $expected): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci') && strlen((string) json_encode($array1)) > 1000) {
            self::markTestSkipped('PDO_OCI cannot bind long strings.');
        }

        self::assertSame($expected, $this->fetchBoolean(
            $this->connection->getDatabasePlatform()->getArraysOverlapExpression(
                $this->getArrayParameterSQL(),
                $this->getArrayParameterSQL(),
            ),
            [$this->getArrayParameter($array1), $this->getArrayParameter($array2)],
        ));
    }

    /** @return iterable<string, array{?list<?string>, ?list<?string>, ?bool}> */
    public static function overlapProvider(): iterable
    {
        yield 'common element' => [['a', 'b'], ['c', 'b'], true];
        yield 'no common element' => [['a', 'b'], ['c'], false];
        yield 'equal arrays' => [['a'], ['a'], true];
        yield 'empty arrays' => [[], [], false];
        yield 'empty array' => [['a'], [], false];
        yield 'NULL first array' => [null, ['a'], null];
        yield 'NULL second array' => [['a'], null, null];
        yield 'NULL elements' => [[null], [null], false];
        yield 'NULL and common elements' => [['a', null], [null, 'a'], true];
        yield 'case' => [['A'], ['a'], false];
        yield 'trailing space' => [['a '], ['a'], false];
        yield 'accents' => [['é'], ['e'], false];
        yield 'multibyte characters' => [['中', '😀'], ['😀'], true];
        yield 'special characters' => [['"\\/', "\n"], ["\n"], true];
        yield 'long strings' => [[str_repeat('x', 5000)], [str_repeat('x', 5000)], true];
        yield 'long strings differing at the end' => [[str_repeat('x', 5000) . 'y'], [str_repeat('x', 5000)], false];
    }

    #[DataProvider('jsonProvider')]
    public function testOverlapOfJsonArrays(string $array1, string $array2, bool $expected): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped('PostgreSQL represents arrays natively.');
        }

        self::assertSame($expected, $this->fetchBoolean(
            $platform->getArraysOverlapExpression('?', '?'),
            [$array1, $array2],
        ));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function jsonProvider(): iterable
    {
        yield 'string and number' => ['["1"]', '[1]', false];
        yield 'numbers' => ['[1, 2]', '[2]', true];
        yield 'booleans' => ['[true]', '[1]', false];
        yield 'escaped characters' => ['["\\u00e9\\/"]', '["é/"]', true];
        yield 'formatting' => ['[ "a" , "b" ]', '["b"]', true];
    }

    public function testOverlapOfColumns(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            // DBAL has no type for the arrays of PostgreSQL
            $this->dropTableIfExists('arrays_overlap_test');
            $this->connection->executeStatement('CREATE TABLE arrays_overlap_test (id INT NOT NULL, tags TEXT[])');
        } else {
            $this->dropAndCreateTable(
                Table::editor()
                    ->setUnquotedName('arrays_overlap_test')
                    ->setColumns(
                        Column::editor()
                            ->setUnquotedName('id')
                            ->setTypeName(Types::INTEGER)
                            ->create(),
                        Column::editor()
                            ->setUnquotedName('tags')
                            ->setTypeName(Types::JSON)
                            ->setNotNull(false)
                            ->create(),
                    )
                    ->create(),
            );
        }

        foreach ([1 => ['php', 'sql'], 2 => ['java'], 3 => null, 4 => ['sql'], 5 => ['SQL', 'PHP']] as $id => $tags) {
            $this->connection->insert('arrays_overlap_test', ['id' => $id, 'tags' => $this->getArrayParameter($tags)]);
        }

        self::assertSame([1, 4], array_map('intval', $this->connection->fetchFirstColumn(
            sprintf(
                'SELECT id FROM arrays_overlap_test WHERE %s = %s ORDER BY id',
                $platform->getArraysOverlapExpression('tags', $this->getArrayParameterSQL()),
                $platform->convertBooleans(true),
            ),
            [$this->getArrayParameter(['sql', 'python'])],
        )));
    }

    /**
     * Returns the SQL of a parameter holding an array: PostgreSQL casts its literal to an array.
     */
    private function getArrayParameterSQL(): string
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return 'CAST(? AS text[])';
        }

        return '?';
    }

    /** @param ?list<?string> $array */
    private function getArrayParameter(?array $array): ?string
    {
        if ($array === null) {
            return null;
        }

        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return '{' . implode(',', array_map(
                static fn (?string $element): string => $element === null
                    ? 'NULL'
                    : '"' . addcslashes($element, '"\\') . '"',
                $array,
            )) . '}';
        }

        return json_encode($array, JSON_THROW_ON_ERROR);
    }

    /** @param list<?string> $params */
    private function fetchBoolean(string $expression, array $params): ?bool
    {
        $platform = $this->connection->getDatabasePlatform();

        return $this->connection->convertToPHPValue(
            $this->connection->fetchOne($platform->getDummySelectSQL($expression), $params),
            Types::BOOLEAN,
        );
    }
}

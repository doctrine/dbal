<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use function sprintf;

class DefaultValueTest extends DbalFunctionalTestCase
{
    /** @var bool */
    private static $initialized = false;

    protected function setUp() : void
    {
        parent::setUp();

        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        $table = new Table('default_value');
        $table->addColumn('id', 'integer');

        foreach (self::columnProvider() as [$name, $default]) {
            $table->addColumn($name, 'string', [
                'default' => $default,
                'notnull' => false,
            ]);
        }

        $this->connection->getSchemaManager()
            ->dropAndCreateTable($table);

        $this->connection->insert('default_value', ['id' => 1]);
    }

    /**
     * @param mixed $expectedDefault
     *
     * @dataProvider columnProvider
     */
    public function testEscapedDefaultValueCanBeIntrospected(string $name, ?string $expectedDefault) : void
    {
        self::assertSame(
            $expectedDefault,
            $this->connection
                ->getSchemaManager()
                ->listTableDetails('default_value')
                ->getColumn($name)
                ->getDefault()
        );
    }

    /**
     * @param mixed $expectedDefault
     *
     * @dataProvider columnProvider
     */
    public function testEscapedDefaultValueCanBeInserted(string $name, ?string $expectedDefault) : void
    {
        $value = $this->connection->fetchColumn(
            sprintf('SELECT %s FROM default_value', $name)
        );

        self::assertSame($expectedDefault, $value);
    }

    /**
     * Returns potential escaped literals from all platforms combined.
     *
     * @see https://dev.mysql.com/doc/refman/5.7/en/string-literals.html
     * @see http://www.sqlite.org/lang_expr.html
     * @see https://www.postgresql.org/docs/9.6/static/sql-syntax-lexical.html#SQL-SYNTAX-STRINGS-ESCAPE
     *
     * @return mixed[][]
     */
    public static function columnProvider() : iterable
    {
        return [
            'Single quote' => [
                'single_quote',
                "foo'bar",
            ],
            'Single quote, doubled' => [
                'single_quote_doubled',
                "foo''bar",
            ],
            'Double quote' => [
                'double_quote',
                'foo"bar',
            ],
            'Double quote, doubled' => [
                'double_quote_doubled',
                'foo""bar',
            ],
            'Backspace' => [
                'backspace',
                "foo\x08bar",
            ],
            'New line' => [
                'new_line',
                "foo\nbar",
            ],
            'Carriage return' => [
                'carriage_return',
                "foo\rbar",
            ],
            'Tab' => [
                'tab',
                "foo\tbar",
            ],
            'Substitute' => [
                'substitute',
                "foo\x1abar",
            ],
            'Backslash' => [
                'backslash',
                'foo\\bar',
            ],
            'Backslash, doubled' => [
                'backslash_doubled',
                'foo\\\\bar',
            ],
            'Percent' => [
                'percent_sign',
                'foo%bar',
            ],
            'Underscore' => [
                'underscore',
                'foo_bar',
            ],
            'NULL string' => [
                'null_string',
                'NULL',
            ],
            'NULL value' => [
                'null_value',
                null,
            ],
            'SQL expression' => [
                'sql_expression',
                "'; DROP DATABASE doctrine --",
            ],
            'No double conversion' => [
                'no_double_conversion',
                "\\'",
            ],
        ];
    }
}

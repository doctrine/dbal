<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

/** @phpstan-import-type Params from DriverManager */
class MySQLInheritCharsetTest extends TestCase
{
    public function testInheritTableOptionsFromDatabase(): void
    {
        // default, no overrides
        $options = $this->getTableOptionsForOverride();
        self::assertFalse(isset($options['charset']));

        // explicit utf8
        $options = $this->getTableOptionsForOverride(['charset' => 'utf8']);
        self::assertTrue(isset($options['charset']));
        self::assertSame($options['charset'], 'utf8');

        // explicit utf8mb4
        $options = $this->getTableOptionsForOverride(['charset' => 'utf8mb4']);
        self::assertTrue(isset($options['charset']));
        self::assertSame($options['charset'], 'utf8mb4');
    }

    public function testTableOptions(): void
    {
        $platform = new MySQLPlatform();

        // no options
        $table = Table::editor()
            ->setUnquotedName('foobar')
            ->setColumns(
                Column::editor()
                ->setUnquotedName('aa')
                ->setTypeName(Types::INTEGER)
                ->create(),
            )
            ->create();

        self::assertSame(
            ['CREATE TABLE foobar (aa INT NOT NULL)'],
            $platform->getCreateTableSQL($table),
        );

        // charset
        $table = Table::editor()
            ->setUnquotedName('foobar')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('aa')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setOptions(['charset' => 'utf8'])
            ->create();

        self::assertSame(
            ['CREATE TABLE foobar (aa INT NOT NULL) DEFAULT CHARACTER SET utf8'],
            $platform->getCreateTableSQL($table),
        );
    }

    /**
     * @param array<string,mixed> $params
     * @phpstan-param Params $params
     *
     * @return string[]
     */
    private function getTableOptionsForOverride(array $params = []): array
    {
        $driverMock = self::createStub(Driver::class);

        $platform = new MySQLPlatform();
        $conn     = new Connection($params, $driverMock, new Configuration());
        $manager  = new MySQLSchemaManager($conn, $platform);

        $schemaConfig = $manager->createSchemaConfig();

        return $schemaConfig->getDefaultTableOptions();
    }
}

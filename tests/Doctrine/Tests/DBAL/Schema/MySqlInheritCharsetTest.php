<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;

use function array_merge;

/**
 * @psalm-import-type Params from DriverManager
 */
class MySqlInheritCharsetTest extends TestCase
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
        $platform = new MySqlPlatform();

        // default, no overrides
        $table = new Table('foobar', [new Column('aa', Type::getType('integer'))]);
        self::assertSame(
            [
                'CREATE TABLE foobar (aa INT NOT NULL)'
                    . ' DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB',
            ],
            $platform->getCreateTableSQL($table)
        );

        // explicit utf8
        $table = new Table('foobar', [new Column('aa', Type::getType('integer'))]);
        $table->addOption('charset', 'utf8');
        self::assertSame(
            [
                'CREATE TABLE foobar (aa INT NOT NULL)'
                    . ' DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB',
            ],
            $platform->getCreateTableSQL($table)
        );

        // explicit utf8mb4
        $table = new Table('foobar', [new Column('aa', Type::getType('integer'))]);
        $table->addOption('charset', 'utf8mb4');
        self::assertSame(
            ['CREATE TABLE foobar (aa INT NOT NULL)'
                    . ' DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            ],
            $platform->getCreateTableSQL($table)
        );
    }

    /**
     * @param array<string,mixed> $params
     * @psalm-param Params $params
     *
     * @return string[]
     */
    private function getTableOptionsForOverride(array $params = []): array
    {
        $eventManager = new EventManager();
        $driverMock   = $this->createMock(Driver::class);
        $platform     = new MySqlPlatform();
        $params       = array_merge(['platform' => $platform], $params);
        $conn         = new Connection($params, $driverMock, new Configuration(), $eventManager);
        $manager      = new MySqlSchemaManager($conn, $platform);

        $schemaConfig = $manager->createSchemaConfig();

        return $schemaConfig->getDefaultTableOptions();
    }
}

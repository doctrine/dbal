<?php

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\TimeType;
use Doctrine\DBAL\Types\Types;
use Iterator;

use function count;
use function get_class;
use function sprintf;

class VariableDateTimePrecisionIntrospectionTest extends FunctionalTestCase
{
    protected AbstractPlatform $platform;

    protected AbstractSchemaManager $schemaManager;

    protected Comparator $comparator;

    protected function setup(): void
    {
        $this->platform = $this->connection->getDatabasePlatform();

        if (
            $this->platform instanceof SqlitePlatform ||
            $this->platform instanceof DB2Platform ||
            $this->platform instanceof OraclePlatform ||
            $this->platform instanceof SQLServerPlatform
        ) {
            self::markTestSkipped(sprintf(
                "Platform %s doesn't support variable precision time",
                get_class($this->platform),
            ));
        }

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    /** @return Iterator<string[]> */
    public static function columnTypeProvider(): Iterator
    {
        foreach (
            [
                Types::DATETIME_MUTABLE,
                Types::DATETIME_IMMUTABLE,
                Types::DATETIMETZ_MUTABLE,
                Types::DATETIMETZ_IMMUTABLE,
                Types::TIME_MUTABLE,
                Types::TIME_IMMUTABLE,
            ] as $type
        ) {
            yield [$type];
        }
    }

    /** @dataProvider columnTypeProvider */
    public function testTableIntrospection(string $type): void
    {
        $tableName = 'test_time_prec_intros';

        $table = new Table($tableName);

        $table->addColumn('col_' . $type . '_0', $type, ['length' => 0]);
        $table->addColumn('col_' . $type . '_3', $type, ['length' => 3]);
        $table->addColumn('col_' . $type . '_6', $type, ['length' => 6]);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable($tableName);

        $diff = $this->comparator->compareTables($table, $onlineTable);

        /*
         * On the Db2 platform, the comparator cannot identify an introspected column as DateTimeTz because the column
         * uses TIMESTAMP, is not commented and so is returned as DateTimeType.
         *
         * On the Oracle platform, the comparator connot identify an introspected column as TimeType because the
         * column uses DATE, is not commented and so is returned as DateType.
         *
         * Where a diff arises on these platforms for this test, ensure it is only an expected type difference and, if
         * so, ignore it because it is not caused by variable precision time.
         */
        $result = $this->platform instanceof DB2Platform || $this->platform instanceof OraclePlatform ?
                  $this->typeOnlyDiff($diff, $this->platform) :
                  $diff->isEmpty();

        self::assertTrue(
            $result,
            sprintf('Tables with columns of type %s should be identical on %s.', $type, get_class($this->platform)),
        );
    }

    /**
     * Whether a comparator difference is caused by a column data type that maps to more than one doctrine type
     * on the given platforms.
     */
    private function typeOnlyDiff(TableDiff $diff, AbstractPlatform $platform): bool
    {
        if (
            $diff->getAddedColumns()        !== [] ||
            $diff->getRenamedColumns()      !== [] ||
            $diff->getDroppedColumns()      !== [] ||
            $diff->getAddedIndexes()        !== [] ||
            $diff->getModifiedIndexes()     !== [] ||
            $diff->getRenamedIndexes()      !== [] ||
            $diff->getDroppedIndexes()      !== [] ||
            $diff->getAddedForeignKeys()    !== [] ||
            $diff->getModifiedForeignKeys() !== [] ||
            $diff->getDroppedForeignKeys()  !== []
        ) {
            return false;
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if (count($columnDiff->changedProperties) === 1 && $columnDiff->changedProperties[0] === 'type') {
                $oldColumn = $columnDiff->getOldColumn();

                if ($oldColumn === null) {
                    return false;
                }

                $oldType = $oldColumn->getType();
                $newType = $columnDiff->getNewColumn()->getType();

                if ($platform instanceof DB2Platform) {
                    if ($oldType instanceof DateTimeTzType && $newType instanceof DateTimeType) {
                        continue;
                    }
                }

                if ($platform instanceof OraclePlatform) {
                    if ($oldType instanceof TimeType && $newType instanceof DateType) {
                        continue;
                    }
                }
            }

            return false;
        }

        return true;
    }
}

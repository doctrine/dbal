<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\SQLite;

use Doctrine\DBAL\Platforms\AbstractDerivedObjectProvider;
use Doctrine\DBAL\Schema\DerivedObject;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Override;

use function count;

/**
 * @internal
 *
 * @link https://www.sqlite.org/lang_createtable.html
 */
final class SQLiteDerivedObjectProvider extends AbstractDerivedObjectProvider
{
    #[Override]
    protected function deriveObjectFromPrimaryKeyConstraint(
        Table $table,
        PrimaryKeyConstraint $constraint,
    ): ?DerivedObject {
        if ($this->isIntegerPrimaryKey($table, $constraint)) {
            return null;
        }

        return parent::deriveObjectFromPrimaryKeyConstraint($table, $constraint);
    }

    /**
     * Returns whether the primary key is an integer primary key, which SQLite implements as an alias
     * for the ROWID instead of an index.
     *
     * SQLite requires the declared type to be exactly INTEGER, so which types qualify follows from
     * what the platform declares them as: {@see SQLitePlatform::getIntegerTypeDeclarationSQL()},
     * {@see SQLitePlatform::getBigIntTypeDeclarationSQL()} and
     * {@see SQLitePlatform::getSmallIntTypeDeclarationSQL()}. Keep this method in sync with them.
     */
    private function isIntegerPrimaryKey(Table $table, PrimaryKeyConstraint $constraint): bool
    {
        $columnNames = $constraint->getColumnNames();

        if (count($columnNames) !== 1) {
            return false;
        }

        $column = $table->getColumn($columnNames[0]->toString());
        $type   = $column->getType();

        if ($type instanceof IntegerType) {
            return true;
        }

        return $column->getAutoincrement() && ($type instanceof SmallIntType || $type instanceof BigIntType);
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\DerivedObject;
use Doctrine\DBAL\Schema\DerivedObjectKind;
use Doctrine\DBAL\Schema\DerivedObjectProvider;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Override;

use function array_merge;

/** @internal */
abstract class AbstractDerivedObjectProvider implements DerivedObjectProvider
{
    /**
     * {@inheritDoc}
     *
     * @return list<DerivedObject>
     */
    #[Override]
    final public function getDerivedObjects(Table $table): array
    {
        return array_merge(
            $this->derive($table, $table->getIndexes(), $this->deriveObjectFromIndex(...)),
            $this->deriveFromPrimaryKeyConstraint($table),
            $this->derive($table, $table->getUniqueConstraints(), $this->deriveObjectFromUniqueConstraint(...)),
            $this->deriveObjectsFromForeignKeyConstraints($table),
        );
    }

    protected function deriveObjectFromIndex(Table $table, Index $index): ?DerivedObject
    {
        return null;
    }

    /** @return list<DerivedObject> */
    private function deriveFromPrimaryKeyConstraint(Table $table): array
    {
        $primaryKeyConstraint = $table->getPrimaryKeyConstraint();

        if ($primaryKeyConstraint === null) {
            return [];
        }

        $derivedObject = $this->deriveObjectFromPrimaryKeyConstraint($table, $primaryKeyConstraint);

        if ($derivedObject === null) {
            return [];
        }

        return [$derivedObject];
    }

    protected function deriveObjectFromPrimaryKeyConstraint(
        Table $table,
        PrimaryKeyConstraint $constraint,
    ): ?DerivedObject {
        return new DerivedObject(DerivedObjectKind::UniqueIndex, $constraint->getColumnNames());
    }

    protected function deriveObjectFromUniqueConstraint(Table $table, UniqueConstraint $constraint): ?DerivedObject
    {
        return new DerivedObject(DerivedObjectKind::UniqueIndex, $constraint->getColumnNames());
    }

    /**
     * The foreign key constraints are derived from as a whole: a platform that indexes them may serve
     * more than one with a single index.
     *
     * @return list<DerivedObject>
     */
    protected function deriveObjectsFromForeignKeyConstraints(Table $table): array
    {
        return [];
    }

    /**
     * @param iterable<T>                        $sources
     * @param callable(Table, T): ?DerivedObject $deriveOne
     *
     * @return list<DerivedObject>
     *
     * @template T
     */
    private function derive(Table $table, iterable $sources, callable $deriveOne): array
    {
        $derivedObjects = [];

        foreach ($sources as $source) {
            $derivedObject = $deriveOne($table, $source);

            if ($derivedObject !== null) {
                $derivedObjects[] = $derivedObject;
            }
        }

        return $derivedObjects;
    }
}

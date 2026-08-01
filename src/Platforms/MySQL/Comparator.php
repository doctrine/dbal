<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\DerivedObjectProvider;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Override;

use function count;

/**
 * Compares schemas in the context of MySQL platform.
 *
 * In MySQL, unless specified explicitly, the column's character set and collation are inherited from its containing
 * table. So during comparison, an omitted value and the value that matches the default value of table in the
 * desired schema must be considered equal.
 */
class Comparator extends BaseComparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(
        AbstractMySQLPlatform $platform,
        DerivedObjectProvider $derivedObjectProvider,
        private readonly CharsetMetadataProvider $charsetMetadataProvider,
        private readonly CollationMetadataProvider $collationMetadataProvider,
        private readonly DefaultTableOptions $defaultTableOptions,
        ComparatorConfig $config = new ComparatorConfig(),
    ) {
        parent::__construct($platform, $derivedObjectProvider, $config);
    }

    #[Override]
    public function compareTables(Table $oldTable, Table $newTable): TableDiff
    {
        return parent::compareTables(
            $this->normalizeTable($oldTable),
            $this->normalizeTable($newTable),
        );
    }

    private function normalizeTable(Table $table): Table
    {
        $charset   = $table->getOption('charset');
        $collation = $table->getOption('collation');

        if ($charset === null && $collation !== null) {
            $charset = $this->collationMetadataProvider->getCollationCharset($collation);
        } elseif ($charset !== null && $collation === null) {
            $collation = $this->charsetMetadataProvider->getDefaultCharsetCollation($charset);
        } elseif ($charset === null && $collation === null) {
            $charset   = $this->defaultTableOptions->getCharset();
            $collation = $this->defaultTableOptions->getCollation();
        }

        $editor = null;

        foreach ($table->getColumns() as $column) {
            $originalCharset   = $column->getCharset();
            $originalCollation = $column->getCollation();

            $normalizedCharset   = $originalCharset;
            $normalizedCollation = $originalCollation;

            if ($originalCharset !== null && $originalCollation === null) {
                $normalizedCollation = $this->charsetMetadataProvider->getDefaultCharsetCollation($originalCharset);
            } elseif ($originalCollation !== null && $originalCharset === null) {
                $normalizedCharset = $this->collationMetadataProvider->getCollationCharset($originalCollation);
            }

            $modifications = [];

            if ($normalizedCharset === $charset) {
                $modifications[] = static function (ColumnEditor $editor): void {
                    $editor->setCharset(null);
                };
            } elseif ($normalizedCharset !== $originalCharset) {
                $modifications[] = static function (ColumnEditor $editor) use ($normalizedCharset): void {
                    $editor->setCharset($normalizedCharset);
                };
            }

            if ($normalizedCollation === $collation) {
                $modifications[] = static function (ColumnEditor $editor): void {
                    $editor->setCollation(null);
                };
            } elseif ($normalizedCollation !== $originalCollation) {
                $modifications[] = static function (ColumnEditor $editor) use ($normalizedCollation): void {
                    $editor->setCollation($normalizedCollation);
                };
            }

            if (count($modifications) === 0) {
                continue;
            }

            $editor ??= $table->edit();
            $name     = $column->getObjectName();

            foreach ($modifications as $modification) {
                $editor->modifyColumn($name, $modification);
            }
        }

        return $editor === null ? $table : $editor->create();
    }
}

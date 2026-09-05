<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

use function array_diff_assoc;
use function array_filter;

/**
 * Compares schemas in the context of MySQL platform.
 *
 * In MySQL, unless specified explicitly, the column's character set and collation are inherited from its containing
 * table. So during comparison, an omitted value and the value that matches the default value of table in the
 * desired schema must be considered equal.
 *
 * @phpstan-import-type PlatformOptions from Column
 */
class Comparator extends BaseComparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(
        AbstractMySQLPlatform $platform,
        private readonly CharsetMetadataProvider $charsetMetadataProvider,
        private readonly CollationMetadataProvider $collationMetadataProvider,
        private readonly DefaultTableOptions $defaultTableOptions,
        ComparatorConfig $config = new ComparatorConfig(),
    ) {
        parent::__construct($platform, $config);
    }

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

        $tableOptions = [
            'charset'   => $charset,
            'collation' => $collation,
        ];

        $table = clone $table;

        $this->normalizeIndexLengths($table);

        foreach ($table->getColumns() as $column) {
            $originalOptions   = $column->getPlatformOptions();
            $normalizedOptions = $this->normalizeOptions($originalOptions);

            $overrideOptions = array_diff_assoc($normalizedOptions, $tableOptions);

            if ($overrideOptions === $originalOptions) {
                continue;
            }

            /** @phpstan-ignore argument.type */
            $column->setPlatformOptions($overrideOptions);
        }

        return $table;
    }

    /**
     * MySQL silently drops an index prefix length that equals the indexed column's length, so introspecting
     * a table declared with such a length returns the index without it. Both representations must be
     * considered equal during comparison.
     */
    private function normalizeIndexLengths(Table $table): void
    {
        foreach ($table->getIndexes() as $index) {
            if (! $index->hasOption('lengths')) {
                continue;
            }

            /** @var array<int, int|null> $lengths */
            $lengths = $index->getOption('lengths');
            $changed = false;

            foreach ($index->getUnquotedColumns() as $position => $columnName) {
                if (! isset($lengths[$position]) || ! $table->hasColumn($columnName)) {
                    continue;
                }

                if ($lengths[$position] !== $table->getColumn($columnName)->getLength()) {
                    continue;
                }

                $lengths[$position] = null;
                $changed            = true;
            }

            if (! $changed) {
                continue;
            }

            $remainingLengths = array_filter($lengths, static fn (?int $length): bool => $length !== null);

            if ($index->isPrimary()) {
                // rebuilding a primary key goes through a dedicated, richer API; prefix lengths on primary
                // keys are exotic enough to leave them as they are
                continue;
            }

            $options = $index->getOptions();
            if ($remainingLengths === []) {
                unset($options['lengths']);
            } else {
                $options['lengths'] = $lengths;
            }

            $table->dropIndex($index->getName());

            if ($index->isUnique()) {
                $table->addUniqueIndex($index->getUnquotedColumns(), $index->getName(), $options);
            } else {
                $table->addIndex($index->getUnquotedColumns(), $index->getName(), $index->getFlags(), $options);
            }
        }
    }

    /**
     * @param PlatformOptions $options
     *
     * @return PlatformOptions
     */
    private function normalizeOptions(array $options): array
    {
        if (isset($options['charset']) && ! isset($options['collation'])) {
            $options['collation'] = $this->charsetMetadataProvider->getDefaultCharsetCollation($options['charset']);
        } elseif (isset($options['collation']) && ! isset($options['charset'])) {
            $options['charset'] = $this->collationMetadataProvider->getCollationCharset($options['collation']);
        }

        return $options;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

use function array_diff_assoc;

/**
 * Compares schemas in the context of MySQL platform.
 *
 * In MySQL, unless specified explicitly, the column's character set and collation are inherited from its containing
 * table. So during comparison, an omitted value and the value that matches the default value of table in the
 * desired schema must be considered equal.
 */
class Comparator extends BaseComparator
{
    private CharsetMetadataProvider $charsetMetadataProvider;

    private CollationMetadataProvider $collationMetadataProvider;

    private DefaultTableOptions $defaultTableOptions;

    /**
     * @internal The comparator can be only instantiated by a schema manager.
     */
    public function __construct(
        AbstractMySQLPlatform $platform,
        CharsetMetadataProvider $charsetMetadataProvider,
        CollationMetadataProvider $collationMetadataProvider,
        DefaultTableOptions $defaultTableOptions
    ) {
        parent::__construct($platform);

        $this->charsetMetadataProvider   = $charsetMetadataProvider;
        $this->collationMetadataProvider = $collationMetadataProvider;
        $this->defaultTableOptions       = $defaultTableOptions;
    }

    public function diffTable(Table $fromTable, Table $toTable): ?TableDiff
    {
        return parent::diffTable(
            $this->normalizeTable($fromTable),
            $this->normalizeTable($toTable)
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

        foreach ($table->getColumns() as $column) {
            $originalOptions   = $column->getPlatformOptions();
            $normalizedOptions = $this->normalizeOptions($originalOptions);

            $overrideOptions = array_diff_assoc($normalizedOptions, $tableOptions);

            if ($overrideOptions === $originalOptions) {
                continue;
            }

            $column->setPlatformOptions($overrideOptions);
        }

        return $table;
    }

    /**
     * @param array<string,string> $options
     *
     * @return array<string,string|null>
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

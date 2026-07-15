<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

use Doctrine\DBAL\Exception\InvalidArgumentException;

/**
 * A row of metadata describing a unique constraint column.
 */
final readonly class UniqueConstraintColumnMetadataRow
{
    private int|string $id;

    /**
     * @param ?non-empty-string         $schemaName
     * @param non-empty-string          $tableName
     * @param int|non-empty-string|null $id         An identifier that groups the columns of the same constraint. Most
     *                                              platforms always report a constraint name (generating one when the
     *                                              constraint was declared unnamed), so the name serves as the
     *                                              identifier. SQLite reports a <code>null</code> constraint name, so
     *                                              this must be set instead.
     * @param ?non-empty-string         $name
     * @param non-empty-string          $columnName
     */
    public function __construct(
        private ?string $schemaName,
        private string $tableName,
        int|string|null $id,
        private ?string $name,
        private string $columnName,
    ) {
        if ($id !== null) {
            $this->id = $id;
        } elseif ($name !== null) {
            $this->id = $name;
        } else {
            throw new InvalidArgumentException(
                'Either the id or name must be set to a non-null value.',
            );
        }
    }

    /** @return ?non-empty-string */
    public function getSchemaName(): ?string
    {
        return $this->schemaName;
    }

    /** @return non-empty-string */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /** @return int|non-empty-string */
    public function getId(): int|string
    {
        return $this->id;
    }

    /** @return ?non-empty-string */
    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return non-empty-string */
    public function getColumnName(): string
    {
        return $this->columnName;
    }
}

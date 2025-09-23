<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Metadata;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;

/**
 * A row of metadata describing a foreign key constraint column.
 */
final readonly class ForeignKeyConstraintColumnMetadataRow
{
    /** @var int|non-empty-string */
    private int|string $id;

    /**
     * @param ?non-empty-string         $referencingSchemaName
     * @param non-empty-string          $referencingTableName
     * @param int|non-empty-string|null $id                    The unique identifier of the foreign key constraint
     *                                                         within its table. Must be populated in all rows if the
     *                                                         source database platform supports unnamed foreign key
     *                                                         constraints and must not otherwise.
     * @param ?non-empty-string         $name                  The name of the foreign key constraint, or null if
     *                                                         the constraint is unnamed.
     * @param ?non-empty-string         $referencedSchemaName
     * @param non-empty-string          $referencedTableName
     * @param non-empty-string          $referencingColumnName
     * @param non-empty-string          $referencedColumnName
     */
    public function __construct(
        private ?string $referencingSchemaName,
        private string $referencingTableName,
        int|string|null $id,
        private ?string $name,
        private ?string $referencedSchemaName,
        private string $referencedTableName,
        private MatchType $matchType,
        private ReferentialAction $onUpdateAction,
        private ReferentialAction $onDeleteAction,
        private bool $isDeferrable,
        private bool $isDeferred,
        private string $referencingColumnName,
        private string $referencedColumnName,
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
        return $this->referencingSchemaName;
    }

    /** @return non-empty-string */
    public function getTableName(): string
    {
        return $this->referencingTableName;
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

    /** @return ?non-empty-string */
    public function getReferencedSchemaName(): ?string
    {
        return $this->referencedSchemaName;
    }

    /** @return non-empty-string */
    public function getReferencedTableName(): string
    {
        return $this->referencedTableName;
    }

    public function getMatchType(): MatchType
    {
        return $this->matchType;
    }

    public function getOnUpdateAction(): ReferentialAction
    {
        return $this->onUpdateAction;
    }

    public function getOnDeleteAction(): ReferentialAction
    {
        return $this->onDeleteAction;
    }

    public function isDeferrable(): bool
    {
        return $this->isDeferrable;
    }

    public function isDeferred(): bool
    {
        return $this->isDeferred;
    }

    /** @return non-empty-string */
    public function getReferencingColumnName(): string
    {
        return $this->referencingColumnName;
    }

    /** @return non-empty-string */
    public function getReferencedColumnName(): string
    {
        return $this->referencedColumnName;
    }
}

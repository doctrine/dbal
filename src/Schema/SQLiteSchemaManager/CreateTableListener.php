<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\SQLiteSchemaManager;

use Doctrine\DBAL\Generated\Context\Column_constraintContext;
use Doctrine\DBAL\Generated\Context\Create_table_stmtContext;
use Doctrine\DBAL\Generated\Context\Table_constraintContext;
use Doctrine\DBAL\Generated\SQLiteParserBaseListener;

use function assert;
use function is_array;

/** @internal */
final class CreateTableListener extends SQLiteParserBaseListener
{
    /** @var list<array<string,mixed>> */
    private array $foreignKeyConstraints = [];

    public function exitCreate_table_stmt(Create_table_stmtContext $context): void
    {
        $columns = $context->column_def();
        assert(is_array($columns));

        foreach ($columns as $column) {
            $columnConstraints = $column->column_constraint();
            assert(is_array($columnConstraints));

            foreach ($columnConstraints as $columnConstraint) {
                $this->parseConstraint($columnConstraint);
            }
        }

        $constraints = $context->table_constraint();
        assert(is_array($constraints));

        foreach ($constraints as $constraint) {
            $this->parseConstraint($constraint);
        }
    }

    private function parseConstraint(Column_constraintContext|Table_constraintContext $context): void
    {
        $foreignKeyClause = $context->foreign_key_clause();
        if ($foreignKeyClause === null) {
            return;
        }

        $this->foreignKeyConstraints[] = [
            'constraint_name' => $context->name()?->getText() ?? '',
            'deferrable' => $foreignKeyClause->DEFERRABLE_() !== null && $foreignKeyClause->NOT_() === null,
            'deferred' => $foreignKeyClause->DEFERRED_() !== null,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function getForeignKeyConstraints(): array
    {
        return $this->foreignKeyConstraints;
    }
}

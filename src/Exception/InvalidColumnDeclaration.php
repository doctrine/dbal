<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use LogicException;

use function sprintf;

final class InvalidColumnDeclaration extends LogicException implements Exception
{
    public string $column;
    public string $table;

    public static function fromInvalidColumnType(string $columnName, InvalidColumnType $e): self
    {
        $e->setColumn($columnName);

        return (new self('Column "%s" has invalid type', 0, $e))->setColumn($columnName);
    }

    public function setColumn(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function updateErrorMessage(): self
    {
        $this->message = sprintf('Column "%s" in table "%s" has invalid type', $this->column, $this->table);

        return $this;
    }
}

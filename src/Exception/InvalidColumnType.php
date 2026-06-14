<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use LogicException;

use function sprintf;

abstract class InvalidColumnType extends LogicException implements Exception
{
    public string $tableName;
    public string $columnName;

    public function updateErrorMessage(): self
    {
        $this->message = sprintf('Column "%s" in table "%s": %s', $this->columnName, $this->tableName, $this->message);

        return $this;
    }

    public function setTable(string $table): self
    {
        $this->tableName = $table;

        return $this;
    }

    public function setColumn(string $column): self
    {
        $this->columnName = $column;

        return $this;
    }
}

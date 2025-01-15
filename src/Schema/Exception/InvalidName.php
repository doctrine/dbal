<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;

use function sprintf;

class InvalidName extends InvalidArgumentException implements SchemaException
{
    public static function fromEmpty(): self
    {
        return new self('Name cannot be empty.');
    }

    public static function fromParserException(string $name, Parser\Exception $parserException): self
    {
        return new self(sprintf('Unable to parse object name "%s".', $name), 0, $parserException);
    }
}

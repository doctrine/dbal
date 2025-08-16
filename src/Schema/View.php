<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;

/**
 * Representation of a Database View.
 *
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
class View extends AbstractNamedObject
{
    public function __construct(string $name, private readonly string $sql)
    {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            $parsedName = $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }

        parent::__construct($parsedName);
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}

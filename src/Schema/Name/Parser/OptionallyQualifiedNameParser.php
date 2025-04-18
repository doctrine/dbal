<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name\Parser;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parser\Exception\InvalidName;

use function count;

/**
 * @internal
 *
 * @implements Parser<OptionallyQualifiedName>
 */
final readonly class OptionallyQualifiedNameParser implements Parser
{
    public function __construct(private GenericNameParser $genericNameParser)
    {
    }

    public function parse(string $input): OptionallyQualifiedName
    {
        $identifiers = $this->genericNameParser->parse($input)
            ->getIdentifiers();

        return match (count($identifiers)) {
            1 => new OptionallyQualifiedName($identifiers[0], null),
            2 => new OptionallyQualifiedName($identifiers[1], $identifiers[0]),
            default => throw InvalidName::forOptionallyQualifiedName(count($identifiers)),
        };
    }
}

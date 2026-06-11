<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name\Parser;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Override;

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

    #[Override]
    public function parse(string $input): OptionallyQualifiedName
    {
        $identifiers = $this->genericNameParser->parse($input, 2);

        if (count($identifiers) === 2) {
            return new OptionallyQualifiedName($identifiers[1], $identifiers[0]);
        }

        return new OptionallyQualifiedName($identifiers[0], null);
    }
}

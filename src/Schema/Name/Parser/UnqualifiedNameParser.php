<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name\Parser;

use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parser\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function count;

/**
 * @internal
 *
 * @implements Parser<UnqualifiedName>
 */
final readonly class UnqualifiedNameParser implements Parser
{
    public function __construct(private GenericNameParser $genericNameParser)
    {
    }

    public function parse(string $input): UnqualifiedName
    {
        $identifiers = $this->genericNameParser->parse($input)
            ->getIdentifiers();

        if (count($identifiers) > 1) {
            throw InvalidName::forUnqualifiedName(count($identifiers));
        }

        return new UnqualifiedName($identifiers[0]);
    }
}

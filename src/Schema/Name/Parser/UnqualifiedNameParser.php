<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name\Parser;

use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Override;

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

    #[Override]
    public function parse(string $input): UnqualifiedName
    {
        $identifiers = $this->genericNameParser->parse($input, 1);

        return new UnqualifiedName($identifiers[0]);
    }
}

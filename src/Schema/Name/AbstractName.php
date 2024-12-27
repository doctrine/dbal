<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Name;

use function array_map;
use function array_merge;
use function array_values;
use function implode;

/**
 * An abstract {@see Name}. Provides the common implementation of the name methods based on the {@see Identifier}s
 * representing the name.
 */
abstract class AbstractName implements Name
{
    /** @var non-empty-list<Identifier> $identifiers */
    protected readonly array $identifiers;

    public function __construct(Identifier $firstIdentifier, Identifier ...$otherIdentifiers)
    {
        $this->identifiers = array_merge([$firstIdentifier], array_values($otherIdentifiers));
    }

    public function toSQL(AbstractPlatform $platform): string
    {
        return $this->joinIdentifiers(static fn (Identifier $identifier): string => $identifier->toSql($platform));
    }

    public function toString(): string
    {
        return $this->joinIdentifiers(static fn (Identifier $identifier): string => $identifier->toString());
    }

    /** @param callable(Identifier): string $mapper */
    private function joinIdentifiers(callable $mapper): string
    {
        return implode('.', array_map($mapper, $this->identifiers));
    }
}

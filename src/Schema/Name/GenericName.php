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
 * A generic {@see Name} consisting of one or more identifiers.
 *
 * @internal
 */
final readonly class GenericName implements Name
{
    /** @var non-empty-list<Identifier> $identifiers */
    private array $identifiers;

    public function __construct(Identifier $firstIdentifier, Identifier ...$otherIdentifiers)
    {
        $this->identifiers = array_merge([$firstIdentifier], array_values($otherIdentifiers));
    }

    /** @return non-empty-list<Identifier> */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    public function toSQL(AbstractPlatform $platform): string
    {
        return $this->joinIdentifiers(static fn (Identifier $identifier): string => $identifier->toSQL($platform));
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

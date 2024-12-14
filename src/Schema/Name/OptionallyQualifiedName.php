<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

/**
 * An optionally qualified {@see Name} consisting of an unqualified name and an optional unqualified qualifier.
 */
final class OptionallyQualifiedName extends AbstractName
{
    public function __construct(private readonly Identifier $unqualifiedName, private readonly ?Identifier $qualifier)
    {
        if ($qualifier !== null) {
            parent::__construct($qualifier, $unqualifiedName);
        } else {
            parent::__construct($unqualifiedName);
        }
    }

    public function getUnqualifiedName(): Identifier
    {
        return $this->unqualifiedName;
    }

    public function getQualifier(): ?Identifier
    {
        return $this->qualifier;
    }
}

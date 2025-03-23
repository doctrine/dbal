<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidPrimaryKeyConstraintDefinition;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use PHPUnit\Framework\TestCase;

class PrimaryKeyConstraintTest extends TestCase
{
    public function testEmptyReferencingColumnNames(): void
    {
        $this->expectException(InvalidPrimaryKeyConstraintDefinition::class);

        /** @phpstan-ignore argument.type */
        new PrimaryKeyConstraint(null, [], false);
    }
}

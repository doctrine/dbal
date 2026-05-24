<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\NamedObject;
use Override;

use function strtolower;

/**
 * A {@see SchemaObjectSet} whose elements all have unqualified names.
 *
 * @internal
 *
 * @template E of NamedObject<OptionallyQualifiedName>
 * @template-extends SchemaObjectSet<E>
 */
final class UnqualifiedSchemaObjectSet extends SchemaObjectSet
{
    #[Override]
    protected function key(OptionallyQualifiedName $name): string
    {
        if ($name->getQualifier() !== null) {
            throw ImproperlyQualifiedName::fromQualifiedName($name);
        }

        return strtolower($name->getUnqualifiedName()->getValue());
    }
}

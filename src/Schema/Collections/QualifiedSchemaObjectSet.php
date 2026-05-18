<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections;

use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\NamedObject;
use Override;

use function strtolower;

/**
 * A {@see SchemaObjectSet} whose elements all have qualified names.
 *
 * @internal
 *
 * @template E of NamedObject<OptionallyQualifiedName>
 * @template-extends SchemaObjectSet<E>
 */
final class QualifiedSchemaObjectSet extends SchemaObjectSet
{
    #[Override]
    protected function key(OptionallyQualifiedName $name): string
    {
        $qualifier = $name->getQualifier();

        if ($qualifier === null) {
            throw ImproperlyQualifiedName::fromUnqualifiedName($name);
        }

        return strtolower($qualifier->getValue()) . "\0" . strtolower($name->getUnqualifiedName()->getValue());
    }
}

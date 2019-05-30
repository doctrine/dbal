<?php

declare(strict_types=1);

namespace Doctrine\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use function strtoupper;

class CommentedType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getName() : string
    {
        return 'my_commented';
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) : string
    {
        return strtoupper($this->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform) : bool
    {
        return true;
    }
}

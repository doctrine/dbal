<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use PDO;

/**
 * Represents a cursor in the database.
 */
class CursorType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        throw new \LogicException('Doctrine does not support SQL declarations for cursors.');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Types::CURSOR;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType()
    {
        return PDO::PARAM_STMT;
    }
}

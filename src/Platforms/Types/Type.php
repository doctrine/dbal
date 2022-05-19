<?php

namespace Doctrine\DBAL\Platforms\Types;

interface Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column);

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value);

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value);
    /**
     * @return bool
     */
    public function requiresSQLCommentHint();
}

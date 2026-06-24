<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

class MySQL90Platform extends MySQL84Platform
{
    /** @inheritdoc */
    public function getVectorTypeDeclarationSQL(array $column): string
    {
        return AbstractMySQLPlatform::getVectorTypeDeclarationSQL($column);
    }
}

<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Types\PhpDateTimeMappingType;
use Doctrine\Deprecations\Deprecation;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 8.0 (8.0 GA) database platform.
 */
class MySQL80Platform extends MySQL57Platform
{
    /** @inheritdoc */
    public function getDateTimeTzTypeDeclarationSQL(array $column)
    {
        return $this->getDateTimeTypeDeclarationSQL($column);
    }

    /** @inheritdoc */
    public function getDateTimeTzFormatString(string $target = PhpDateTimeMappingType::CONVERSION_TARGET_DATABASE)
    {
        if ($target === PhpDateTimeMappingType::CONVERSION_TARGET_DATABASE) {
            return 'Y-m-d H:i:sP';
        }

        return 'Y-m-d H:i:s';
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Implement {@see createReservedKeywordsList()} instead.
     */
    protected function getReservedKeywordsClass()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4510',
            'MySQL80Platform::getReservedKeywordsClass() is deprecated,'
                . ' use MySQL80Platform::createReservedKeywordsList() instead.',
        );

        return Keywords\MySQL80Keywords::class;
    }
}

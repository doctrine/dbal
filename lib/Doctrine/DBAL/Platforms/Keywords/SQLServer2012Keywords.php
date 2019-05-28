<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Keywords;

use function array_merge;

/**
 * Microsoft SQL Server 2012 reserved keyword dictionary.
 *
 * @link    www.doctrine-project.com
 */
class SQLServer2012Keywords extends SQLServerKeywords
{
    /**
     * {@inheritdoc}
     */
    public function getName() : string
    {
        return 'SQLServer2012';
    }

    /**
     * {@inheritdoc}
     *
     * @link http://msdn.microsoft.com/en-us/library/ms189822.aspx
     */
    protected function getKeywords() : array
    {
        return array_merge(parent::getKeywords(), [
            'SEMANTICKEYPHRASETABLE',
            'SEMANTICSIMILARITYDETAILSTABLE',
            'SEMANTICSIMILARITYTABLE',
            'TRY_CONVERT',
            'WITHIN GROUP',
        ]);
    }
}

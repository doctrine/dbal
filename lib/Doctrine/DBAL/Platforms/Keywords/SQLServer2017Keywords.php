<?php

namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * Microsoft SQL Server 2017 reserved keyword dictionary.
 *
 * @license BSD http://www.opensource.org/licenses/bsd-license.php
 * @link    www.doctrine-project.com
 */
class SQLServer2017Keywords extends SQLServer2012Keywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'SQLServer2017';
    }

    /**
     * {@inheritdoc}
     */
    protected function getKeywords()
    {
        return array_merge(parent::getKeywords(), [
            'NOCHECK'
        ]);
    }
}

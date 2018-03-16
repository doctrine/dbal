<?php

namespace Doctrine\DBAL\Platforms\Keywords;

use function array_merge;

/**
 * Microsoft SQL Server 2012 reserved keyword dictionary.
 *
 * @license BSD http://www.opensource.org/licenses/bsd-license.php
 * @link    www.doctrine-project.com
 * @since   2.3
 * @author  Steve Müller <st.mueller@dzh-online.de>
 */
class SQLServer2012Keywords extends SQLServerKeywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'SQLServer2012';
    }

    /**
     * {@inheritdoc}
     *
     * @link http://msdn.microsoft.com/en-us/library/ms189822.aspx
     */
    protected function getKeywords()
    {
        return array_merge(parent::getKeywords(), [
            'SEMANTICKEYPHRASETABLE',
            'SEMANTICSIMILARITYDETAILSTABLE',
            'SEMANTICSIMILARITYTABLE',
            'TRY_CONVERT',
            'WITHIN GROUP'
        ]);
    }
}

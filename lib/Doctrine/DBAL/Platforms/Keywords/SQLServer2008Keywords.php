<?php

namespace Doctrine\DBAL\Platforms\Keywords;

use function array_merge;

/**
 * Microsoft SQL Server 2008 reserved keyword dictionary.
 *
 * @license BSD http://www.opensource.org/licenses/bsd-license.php
 * @link    www.doctrine-project.com
 * @since   2.3
 * @author  Steve Müller <st.mueller@dzh-online.de>
 */
class SQLServer2008Keywords extends SQLServer2005Keywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'SQLServer2008';
    }

    /**
     * {@inheritdoc}
     *
     * @link http://msdn.microsoft.com/en-us/library/ms189822%28v=sql.100%29.aspx
     */
    protected function getKeywords()
    {
        return array_merge(parent::getKeywords(), [
            'MERGE'
        ]);
    }
}

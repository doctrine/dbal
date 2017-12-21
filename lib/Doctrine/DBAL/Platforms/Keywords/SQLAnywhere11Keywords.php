<?php

namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * SAP Sybase SQL Anywhere 11 reserved keywords list.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 */
class SQLAnywhere11Keywords extends SQLAnywhereKeywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'SQLAnywhere11';
    }

    /**
     * {@inheritdoc}
     *
     * @link http://dcx.sybase.com/1100/en/dbreference_en11/alhakeywords.html
     */
    protected function getKeywords()
    {
        return array_merge(
            array_diff(
                parent::getKeywords(),
                ['IQ']
            ),
            [
                'MERGE',
                'OPENSTRING'
            ]
        );
    }
}

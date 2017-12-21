<?php

namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * PostgreSQL 9.2 reserved keywords list.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class PostgreSQL92Keywords extends PostgreSQL91Keywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'PostgreSQL92';
    }

    /**
     * {@inheritdoc}
     *
     * @link http://www.postgresql.org/docs/9.2/static/sql-keywords-appendix.html
     */
    protected function getKeywords()
    {
        return array_merge(parent::getKeywords(), [
            'COLLATION',
        ]);
    }
}

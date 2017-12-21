<?php


namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * MsSQL Keywordlist
 *
 * @license    BSD http://www.opensource.org/licenses/bsd-license.php
 * @link       www.doctrine-project.com
 * @since      2.0
 * @author     Benjamin Eberlei <kontakt@beberlei.de>
 * @author     David Coallier <davidc@php.net>
 * @author     Steve Müller <st.mueller@dzh-online.de>
 * @deprecated Use SQLServerKeywords class instead.
 */
class MsSQLKeywords extends SQLServerKeywords
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'MsSQL';
    }
}

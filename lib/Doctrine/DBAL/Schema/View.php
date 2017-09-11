<?php

namespace Doctrine\DBAL\Schema;

/**
 * Representation of a Database View.
 *
 * @link   www.doctrine-project.org
 * @since  1.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class View extends AbstractAsset
{
    /**
     * @var string
     */
    private $_sql;

    /**
     * @param string $name
     * @param string $sql
     */
    public function __construct($name, $sql)
    {
        $this->_setName($name);
        $this->_sql = $sql;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->_sql;
    }
}

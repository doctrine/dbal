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
    private $sql;

    /**
     * @param string $name
     * @param string $sql
     */
    public function __construct($name, $sql)
    {
        $this->setName($name);
        $this->sql = $sql;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }
}

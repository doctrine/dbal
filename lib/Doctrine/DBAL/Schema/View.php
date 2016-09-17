<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Visitor\ViewVisitor;
use Doctrine\DBAL\Schema\Visitor\Visitor;

/**
 * Representation of a Database View.
 */
class View extends AbstractAsset
{
    /** @var string */
    private $sql;

    /**
     * @param string $name
     * @param string $sql
     */
    public function __construct($name, $sql)
    {
        $this->_setName($name);
        $this->sql = $sql;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * @return void
     */
    public function visit(Visitor $visitor)
    {
        if (! ($visitor instanceof ViewVisitor)) {
            return;
        }

        $visitor->acceptView($this);
    }

    public function isSameAs(View $anotherView)
    {
        return $anotherView->getSql() === $this->getSql();
    }
}

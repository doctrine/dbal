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

    public function __construct(string $name, string $sql)
    {
        $this->_setName($name);
        $this->sql = $sql;
    }

    public function getSql() : string
    {
        return $this->sql;
    }

    public function visit(Visitor $visitor) : void
    {
        if (! $visitor instanceof ViewVisitor) {
            return;
        }

        $visitor->acceptView($this);
    }

    public function isSameAs(View $anotherView) : bool
    {
        return $anotherView->getSql() === $this->getSql();
    }
}

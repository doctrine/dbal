<?php

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\View;

/**
 * Visitor that can visit views.
 */
interface ViewVisitor
{
    public function acceptView(View $view) : void;
}

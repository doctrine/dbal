<?php

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\View;

/**
 * Visitor that can visit views.
 *
 * @link   www.doctrine-project.org
 */
interface ViewVisitor
{
    /**
     * @return void
     */
    public function acceptView(View $view);
}

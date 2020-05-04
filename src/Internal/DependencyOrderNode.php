<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Internal;

class DependencyOrderNode
{
    /** @var string */
    public $hash;

    /** @var int */
    public $state;

    /** @var object */
    public $value;

    /** @var DependencyOrderEdge[] */
    public $dependencyList = [];
}

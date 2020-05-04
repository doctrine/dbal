<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Internal;

class DependencyOrderEdge
{
    /** @var string */
    public $from;

    /** @var string */
    public $to;
}

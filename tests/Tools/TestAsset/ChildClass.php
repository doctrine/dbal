<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Tools\TestAsset;

final class ChildClass extends ParentClass
{
    /** @var int */
    public $childPublicAttribute = 4;

    /** @var int */
    protected $childProtectedAttribute = 5;

    /** @var int */
    private $childPrivateAttribute = 6;
}

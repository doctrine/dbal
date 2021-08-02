<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Tools\TestAsset;

final class ChildWithSameAttributesClass extends ParentClass
{
    public int $parentPublicAttribute = 4;

    protected int $parentProtectedAttribute = 5;

    private int $parentPrivateAttribute = 6;
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

final class AbstractPlatformExtensionTest extends TestCase
{
    use VerifyDeprecations;

    /**
     * This test uses deprecated PHPUnit features and can be removed during the PHPUnit upgrade.
     */
    public function testNotPassingUnquotedIdentifierFolding(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6823');

        $this->getMockForAbstractClass(AbstractPlatform::class);
    }

    /**
     * This test uses deprecated PHPUnit features and can be removed during the PHPUnit upgrade.
     */
    public function testNotCallingParentConstructor(): void
    {
        $platform = $this->getMockForAbstractClass(AbstractPlatform::class, [], '', false);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6823');

        $platform->getUnquotedIdentifierFolding();
    }
}

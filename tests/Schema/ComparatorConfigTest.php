<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ComparatorConfigTest extends TestCase
{
    use VerifyDeprecations;

    public function testEnableReportingOfModifiedIndexes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ComparatorConfig())->withReportModifiedIndexes(true);
    }

    public function testDisableReportingOfModifiedIndexes(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6898');
        (new ComparatorConfig())->withReportModifiedIndexes(false);
    }
}

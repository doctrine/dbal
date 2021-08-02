<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the configuration container.
 */
class ConfigurationTest extends TestCase
{
    /**
     * The configuration container instance under test.
     */
    protected Configuration $config;

    protected function setUp(): void
    {
        $this->config = new Configuration();
    }

    /**
     * Tests that the default auto-commit mode for connections can be retrieved from the configuration container.
     */
    public function testReturnsDefaultConnectionAutoCommitMode(): void
    {
        self::assertTrue($this->config->getAutoCommit());
    }

    /**
     * Tests that the default auto-commit mode for connections can be set in the configuration container.
     */
    public function testSetsDefaultConnectionAutoCommitMode(): void
    {
        $this->config->setAutoCommit(false);

        self::assertFalse($this->config->getAutoCommit());
    }
}

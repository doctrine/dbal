<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Configuration;
use Doctrine\Tests\DbalTestCase;

/**
 * Unit tests for the configuration container.
 */
class ConfigurationTest extends DbalTestCase
{
    /**
     * The configuration container instance under test.
     *
     * @var Configuration
     */
    protected $config;

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

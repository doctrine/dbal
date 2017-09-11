<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Configuration;
use Doctrine\Tests\DbalTestCase;

/**
 * Unit tests for the configuration container.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 */
class ConfigurationTest extends DbalTestCase
{
    /**
     * The configuration container instance under test.
     *
     * @var \Doctrine\DBAL\Configuration
     */
    protected $config;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->config = new Configuration();
    }

    /**
     * Tests that the default auto-commit mode for connections can be retrieved from the configuration container.
     *
     * @group DBAL-81
     */
    public function testReturnsDefaultConnectionAutoCommitMode()
    {
        $this->assertTrue($this->config->getAutoCommit());
    }

    /**
     * Tests that the default auto-commit mode for connections can be set in the configuration container.
     *
     * @group DBAL-81
     */
    public function testSetsDefaultConnectionAutoCommitMode()
    {
        $this->config->setAutoCommit(false);

        $this->assertFalse($this->config->getAutoCommit());

        $this->config->setAutoCommit(0);

        $this->assertFalse($this->config->getAutoCommit());
    }
}

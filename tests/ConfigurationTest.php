<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Doctrine\DBAL\Types\TypeRegistry;
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

    public function testGetTypeProviderReturnsGlobalRegistryByDefault(): void
    {
        self::assertSame(Type::getTypeRegistry(), $this->config->getTypeProvider());
    }

    public function testSetTypeProviderReplacesRegistry(): void
    {
        $registry = new TypeRegistry();
        $this->config->setTypeProvider($registry);

        self::assertSame($registry, $this->config->getTypeProvider());
    }

    public function testSetTypeProviderReturnsSelf(): void
    {
        self::assertSame($this->config, $this->config->setTypeProvider(new TypeRegistry()));
    }

    public function testAnyTypeProviderImplementationIsAccepted(): void
    {
        // A hand-written registry is enough: TypeRegistry is what makes the registry substitutable.
        $provider = self::createStub(TypeProvider::class);
        $this->config->setTypeProvider($provider);

        self::assertSame($provider, $this->config->getTypeProvider());
    }
}

<?php

namespace Doctrine\Tests\DBAL;

use PHPUnit\Framework\MockObject\MockBuilder;

use function method_exists;

/**
 * @template T
 */
class MockBuilderProxy
{
    /** @var MockBuilder<T> */
    private $originalMockBuilder;

    /**
     * @param MockBuilder<T> $originalMockBuilder
     */
    public function __construct(MockBuilder $originalMockBuilder)
    {
        $this->originalMockBuilder = $originalMockBuilder;
    }

    /**
     * @param array<string> $methods
     *
     * @return MockBuilder<T>
     */
    public function onlyMethods(array $methods): MockBuilder
    {
        if (method_exists(MockBuilder::class, 'onlyMethods')) {
            return $this->originalMockBuilder->onlyMethods($methods);
        }

        return $this->originalMockBuilder->setMethods($methods);
    }
}

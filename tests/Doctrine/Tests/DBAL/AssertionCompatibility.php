<?php

namespace Doctrine\Tests\DBAL;

trait AssertionCompatibility
{
    /**
     * @param array<mixed> $arguments
     *
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'assertMatchesRegularExpression') {
            self::assertRegExp(...$arguments);
        } elseif ($name === 'assertFileDoesNotExist') {
            self::assertFileNotExists(...$arguments);
        }

        return null;
    }

    /**
     * @param array<mixed> $arguments
     *
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        if ($method === 'createStub') {
            return $this->getMockBuilder(...$arguments)
                ->disableOriginalConstructor()
                ->disableOriginalClone()
                ->disableArgumentCloning()
                ->disallowMockingUnknownTypes()
                ->getMock();
        }

        return null;
    }
}

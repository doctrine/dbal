<?php

/**
 * @see https://confluence.jetbrains.com/display/PhpStorm/PhpStorm+Advanced+Metadata
 */
namespace PHPSTORM_META
{
    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;

    override(
        TestCase::createMock(0),
        map([
            '@&' . MockObject::class,
        ])
    );

    override(
        TestCase::createPartialMock(0),
        map([
            '@&' . MockObject::class,
        ])
    );

    override(
        TestCase::getMockForAbstractClass(0),
        map([
            '@&' . MockObject::class,
        ])
    );
}

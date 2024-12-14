<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Schema\Name\Parser\GenericNameParser;
use Doctrine\DBAL\Schema\Name\Parser\OptionallyQualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parser\UnqualifiedNameParser;

/**
 * A static registry for name parsers.
 *
 * @internal This class should be used by {@link AbstractAsset} subclasses only.
 */
final class Parsers
{
    private static ?UnqualifiedNameParser $unqualifiedNameParser = null;

    private static ?OptionallyQualifiedNameParser $optionallyQualifiedNameParser = null;

    private static ?GenericNameParser $genericNameParser = null;

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }

    public static function getUnqualifiedNameParser(): UnqualifiedNameParser
    {
        return self::$unqualifiedNameParser ??= new UnqualifiedNameParser(self::getGenericNameParser());
    }

    public static function getOptionallyQualifiedNameParser(): OptionallyQualifiedNameParser
    {
        return self::$optionallyQualifiedNameParser ??= new OptionallyQualifiedNameParser(self::getGenericNameParser());
    }

    public static function getGenericNameParser(): GenericNameParser
    {
        return self::$genericNameParser ??= new GenericNameParser();
    }
}

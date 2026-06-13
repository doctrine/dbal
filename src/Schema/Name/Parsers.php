<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Schema\Name\Parser\Exception;
use Doctrine\DBAL\Schema\Name\Parser\GenericNameParser;
use Doctrine\DBAL\Schema\Name\Parser\OptionallyQualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parser\UnqualifiedNameParser;

/**
 * A static registry for name parsers.
 *
 * @internal This class should be used by schema object classes only.
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

    /** @throws Exception */
    public static function parseUnqualifiedName(string $input): UnqualifiedName
    {
        $parser = self::$unqualifiedNameParser
              ??= new UnqualifiedNameParser(self::getGenericNameParser());

        return $parser->parse($input);
    }

    /** @throws Exception */
    public static function parseOptionallyQualifiedName(string $input): OptionallyQualifiedName
    {
        $parser = self::$optionallyQualifiedNameParser
              ??= new OptionallyQualifiedNameParser(self::getGenericNameParser());

        return $parser->parse($input);
    }

    private static function getGenericNameParser(): GenericNameParser
    {
        return self::$genericNameParser ??= new GenericNameParser();
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name\Parser;

use Doctrine\DBAL\Schema\Name\GenericName;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parser\Exception\ExpectedDot;
use Doctrine\DBAL\Schema\Name\Parser\Exception\ExpectedNextIdentifier;
use Doctrine\DBAL\Schema\Name\Parser\Exception\UnableToParseIdentifier;

use function assert;
use function count;
use function preg_match;
use function str_replace;
use function strlen;

/**
 * Parses a generic qualified or unqualified SQL-like name.
 *
 * A name can be either unqualified or qualified:
 * - An unqualified name consists of a single identifier.
 * - A qualified name is a sequence of two or more identifiers separated by dots.
 *
 * An identifier can be quoted or unquoted:
 * - A quoted identifier is enclosed in double quotes ("), backticks (`), or square brackets ([]).
 * The closing quote character can be escaped by doubling it.
 * - An unquoted identifier may contain any character except whitespace, dots, or any of the quote characters.
 *
 * Differences from SQL:
 * 1. Identifiers that are reserved keywords or start with a digit do not need to be quoted.
 * 2. Whitespace is not allowed between identifiers.
 *
 * @internal
 *
 * @implements Parser<GenericName>
 */
final class GenericNameParser implements Parser
{
    private const IDENTIFIER_PATTERN = <<<'PATTERN'
        /\G
        (?:
            "(?<ansi>[^"]*(?:""[^"]*)*)"         # ANSI SQL double-quoted
          | `(?<mysql>[^`]*(?:``[^`]*)*)`        # MySQL-style backtick-quoted
          | \[(?<sqlserver>[^]]*(?:]][^]]*)*)]   # SQL Server-style square-bracket-quoted
          | (?<unquoted>[^\s."`\[\]]+)           # Unquoted
        )
        /x
    PATTERN;

    public function parse(string $input): GenericName
    {
        $offset      = 0;
        $identifiers = [];
        $length      = strlen($input);

        while (true) {
            if ($offset >= $length) {
                throw ExpectedNextIdentifier::new();
            }

            if (preg_match(self::IDENTIFIER_PATTERN, $input, $matches, 0, $offset) === 0) {
                throw UnableToParseIdentifier::new($offset);
            }

            if (isset($matches['ansi']) && strlen($matches['ansi']) > 0) {
                $identifier = Identifier::quoted(str_replace('""', '"', $matches['ansi']));
            } elseif (isset($matches['mysql']) && strlen($matches['mysql']) > 0) {
                $identifier = Identifier::quoted(str_replace('``', '`', $matches['mysql']));
            } elseif (isset($matches['sqlserver']) && strlen($matches['sqlserver']) > 0) {
                $identifier = Identifier::quoted(str_replace(']]', ']', $matches['sqlserver']));
            } else {
                assert(isset($matches['unquoted']) && strlen($matches['unquoted']) > 0);
                $identifier = Identifier::unquoted($matches['unquoted']);
            }

            $identifiers[] = $identifier;

            $offset += strlen($matches[0]);

            if ($offset >= $length) {
                break;
            }

            $character = $input[$offset];

            if ($character !== '.') {
                throw ExpectedDot::new($offset, $character);
            }

            $offset++;
        }

        assert(count($identifiers) > 0);

        return new GenericName(...$identifiers);
    }
}

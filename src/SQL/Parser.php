<?php

namespace Doctrine\DBAL\SQL;

use Doctrine\DBAL\SQL\Parser\Exception;
use Doctrine\DBAL\SQL\Parser\Exception\RegularExpressionError;
use Doctrine\DBAL\SQL\Parser\Visitor;

use function array_keys;
use function array_merge;
use function array_values;
use function assert;
use function current;
use function implode;
use function is_array;
use function is_string;
use function key;
use function next;
use function preg_last_error;
use function preg_match;
use function preg_replace;
use function preg_split;
use function reset;
use function sprintf;
use function strlen;

use const PREG_NO_ERROR;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

/**
 * The SQL parser that focuses on identifying prepared statement parameters. It implements parsing other tokens like
 * string literals and comments only as a way to not confuse their contents with the the parameter placeholders.
 *
 * The parsing logic and the implementation is inspired by the PHP PDO parser.
 *
 * @internal
 *
 * @see https://github.com/php/php-src/blob/php-7.4.12/ext/pdo/pdo_sql_parser.re#L49-L69
 */
final class Parser
{
    private const SPECIAL_CHARS = ':\?\'"`\\[\\-\\/';

    private const BACKTICK_IDENTIFIER  = '`[^`]*`';
    private const BRACKET_IDENTIFIER   = '(?<!\b(?i:ARRAY))\[(?:[^\]])*\]';
    private const MULTICHAR            = ':{2,}';
    private const NAMED_PARAMETER      = ':[a-zA-Z0-9_]+';
    private const POSITIONAL_PARAMETER = '(?<!\\?)\\?(?!\\?)';
    private const ONE_LINE_COMMENT     = '--[^\r\n]*';
    private const MULTI_LINE_COMMENT   = '/\*([^*]+|\*+[^/*])*\**\*/';
    private const SPECIAL              = '[' . self::SPECIAL_CHARS . ']';
    private const OTHER                = '[^' . self::SPECIAL_CHARS . ']+';

    private const REPLACE_PATTERNS = ['/\b([Aa][Rr][Rr][Aa][Yy])\s*\[/' => '\1['];

    private const STRING_LITERAL_DELIMITER = "/(['\"])((?:\\\\\\1|(?:(?!\\1)).)*?)\\1(*SKIP)(*F)|([^'\"]+)/";

    private const STRING_LITERAL = '/^([\'"`])(?:\\\\\\1|(?:(?!\1)).)*\1$/';

    private string $sqlPattern;

    public function __construct(bool $mySQLStringEscaping)
    {
        if ($mySQLStringEscaping) {
            $patterns = [
                $this->getMySQLStringLiteralPattern("'"),
                $this->getMySQLStringLiteralPattern('"'),
            ];
        } else {
            $patterns = [
                $this->getAnsiSQLStringLiteralPattern("'"),
                $this->getAnsiSQLStringLiteralPattern('"'),
            ];
        }

        $patterns = array_merge($patterns, [
            self::BACKTICK_IDENTIFIER,
            self::BRACKET_IDENTIFIER,
            self::MULTICHAR,
            self::ONE_LINE_COMMENT,
            self::MULTI_LINE_COMMENT,
            self::OTHER,
        ]);

        $this->sqlPattern = sprintf('(%s)', implode('|', $patterns));
    }

    /**
     * Parses the given SQL statement
     *
     * @throws Exception
     */
    public function parse(string $sql, Visitor $visitor): void
    {
        $parts = preg_split(self::STRING_LITERAL_DELIMITER, $sql, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (is_array($parts)) {
            foreach ($parts as &$part) {
                if (preg_match(self::STRING_LITERAL, $part) !== 0) {
                    continue;
                }

                $part = preg_replace(
                    array_keys(self::REPLACE_PATTERNS),
                    array_values(self::REPLACE_PATTERNS),
                    $part,
                );
            }

            unset($part);
            $sql = implode('', $parts);
        }

        assert(is_string($sql));

        /** @var array<string,callable> $patterns */
        $patterns = [
            self::NAMED_PARAMETER => static function (string $sql) use ($visitor): void {
                $visitor->acceptNamedParameter($sql);
            },
            self::POSITIONAL_PARAMETER => static function (string $sql) use ($visitor): void {
                $visitor->acceptPositionalParameter($sql);
            },
            $this->sqlPattern => static function (string $sql) use ($visitor): void {
                $visitor->acceptOther($sql);
            },
            self::SPECIAL => static function (string $sql) use ($visitor): void {
                $visitor->acceptOther($sql);
            },
        ];

        $offset = 0;

        while (($handler = current($patterns)) !== false) {
            if (preg_match('~\G' . key($patterns) . '~s', $sql, $matches, 0, $offset) === 1) {
                $handler($matches[0]);
                reset($patterns);

                $offset += strlen($matches[0]);
            } elseif (preg_last_error() !== PREG_NO_ERROR) {
                // @codeCoverageIgnoreStart
                throw RegularExpressionError::new();
                // @codeCoverageIgnoreEnd
            } else {
                next($patterns);
            }
        }

        assert($offset === strlen($sql));
    }

    private function getMySQLStringLiteralPattern(string $delimiter): string
    {
        return $delimiter . '((\\\\.)|(?![' . $delimiter . '\\\\]).)*' . $delimiter;
    }

    private function getAnsiSQLStringLiteralPattern(string $delimiter): string
    {
        return $delimiter . '[^' . $delimiter . ']*' . $delimiter;
    }
}

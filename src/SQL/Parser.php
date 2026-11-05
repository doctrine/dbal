<?php

declare(strict_types=1);

namespace Doctrine\DBAL\SQL;

use Doctrine\DBAL\SQL\Parser\Exception\RegularExpressionError;
use Doctrine\DBAL\SQL\Parser\Visitor;

use function array_merge;
use function implode;
use function preg_last_error;
use function preg_match;
use function sprintf;
use function strlen;

use const PREG_NO_ERROR;

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
    private const OTHER                = '[^' . self::SPECIAL_CHARS . ']++';

    /**
     * How many consecutive non-parameter tokens one preg_match() call may consume.
     *
     * PCRE compiles a bounded repetition of a group into that many copies of the group, so the bound is
     * limited by the maximum compiled pattern size (192 is already too large). An unbounded repetition
     * would exhaust the backtracking limit on multi-megabyte statements when the JIT is disabled.
     */
    private const MAX_TOKENS_PER_MATCH = 64;

    private readonly string $sqlPattern;
    private readonly string $tokenPattern;

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

        // The "other" group consumes a run of up to MAX_TOKENS_PER_MATCH non-parameter tokens instead of a single
        // one. None of the alternatives in $sqlPattern can start where a parameter starts (':' and '?' are in
        // SPECIAL_CHARS and no alternative begins with them), so the run always stops in front of the next
        // parameter and the visitor receives the same parameters and the same SQL text as before, in fewer
        // and larger acceptOther() calls. On a SELECT list of hundreds of quoted identifiers, this cuts the
        // number of preg_match() calls by about 98%.
        $this->tokenPattern = '~\\G'
            . '(?P<named>' . self::NAMED_PARAMETER . ')'
            . '|(?P<positional>' . self::POSITIONAL_PARAMETER . ')'
            . '|(?P<other>(?:' . $this->sqlPattern . '){1,' . self::MAX_TOKENS_PER_MATCH . '}+|' . self::SPECIAL . ')'
            . '~s';
    }

    /**
     * Parses the given SQL statement
     */
    public function parse(string $sql, Visitor $visitor): void
    {
        $offset = 0;
        $length = strlen($sql);
        while ($offset < $length) {
            if (preg_match($this->tokenPattern, $sql, $matches, 0, $offset) === 1) {
                $match = $matches[0];
                if ($matches['named'] !== '') {
                    $visitor->acceptNamedParameter($match);
                } elseif ($matches['positional'] !== '') {
                    $visitor->acceptPositionalParameter($match);
                } else {
                    $visitor->acceptOther($match);
                }

                $offset += strlen($match);
            } elseif (preg_last_error() !== PREG_NO_ERROR) {
                // @codeCoverageIgnoreStart
                throw RegularExpressionError::new();
                // @codeCoverageIgnoreEnd
            }
        }
    }

    private function getMySQLStringLiteralPattern(string $delimiter): string
    {
        // A literal is a run of characters that are neither the delimiter nor a backslash, interleaved with
        // escape sequences (a backslash followed by any character). The quantifiers are possessive: nothing
        // after a literal can require the engine to back into it, so PCRE does not need to keep a backtracking
        // frame per character. The previous form, '((\\.)|(?![\'\\]).)*', did, which limited a literal to
        // roughly 6 KB under the PCRE JIT before preg_match() failed with "JIT stack limit exhausted".
        return $delimiter . '[^' . $delimiter . '\\\\]*+(?:\\\\.[^' . $delimiter . '\\\\]*+)*+' . $delimiter;
    }

    private function getAnsiSQLStringLiteralPattern(string $delimiter): string
    {
        return $delimiter . '[^' . $delimiter . ']*' . $delimiter;
    }
}

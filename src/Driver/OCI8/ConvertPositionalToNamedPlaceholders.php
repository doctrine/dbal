<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use function count;
use function implode;
use function preg_match;
use function preg_quote;
use function substr;

use const PREG_OFFSET_CAPTURE;

/**
 * Converts positional (?) into named placeholders (:param<num>).
 *
 * Oracle does not support positional parameters, hence this method converts all
 * positional parameters into artificially named parameters. Note that this conversion
 * is not perfect. All question marks (?) in the original statement are treated as
 * placeholders and converted to a named parameter.
 *
 * @internal This class is not covered by the backward compatibility promise
 */
final class ConvertPositionalToNamedPlaceholders
{
    /**
     * @param string $statement The SQL statement to convert.
     *
     * @return mixed[] [0] => the statement value (string), [1] => the paramMap value (array).
     *
     * @throws OCI8Exception
     */
    public function __invoke(string $statement): array
    {
        $fragmentOffset          = $tokenOffset = 0;
        $fragments               = $paramMap = [];
        $currentLiteralDelimiter = null;

        do {
            if ($currentLiteralDelimiter === null) {
                $result = $this->findPlaceholderOrOpeningQuote(
                    $statement,
                    $tokenOffset,
                    $fragmentOffset,
                    $fragments,
                    $currentLiteralDelimiter,
                    $paramMap
                );
            } else {
                $result = $this->findClosingQuote($statement, $tokenOffset, $currentLiteralDelimiter);
            }
        } while ($result);

        if ($currentLiteralDelimiter) {
            throw NonTerminatedStringLiteral::new($tokenOffset - 1);
        }

        $fragments[] = substr($statement, $fragmentOffset);
        $statement   = implode('', $fragments);

        return [$statement, $paramMap];
    }

    /**
     * Finds next placeholder or opening quote.
     *
     * @param string      $statement               The SQL statement to parse
     * @param int         $tokenOffset             The offset to start searching from
     * @param int         $fragmentOffset          The offset to build the next fragment from
     * @param string[]    $fragments               Fragments of the original statement not containing placeholders
     * @param string|null $currentLiteralDelimiter The delimiter of the current string literal
     *                                             or NULL if not currently in a literal
     * @param string[]    $paramMap                Mapping of the original parameter positions to their named replacements
     *
     * @return bool Whether the token was found
     */
    private function findPlaceholderOrOpeningQuote(
        string $statement,
        int &$tokenOffset,
        int &$fragmentOffset,
        array &$fragments,
        ?string &$currentLiteralDelimiter,
        array &$paramMap
    ): bool {
        $token = $this->findToken($statement, $tokenOffset, '/[?\'"]/');

        if ($token === null) {
            return false;
        }

        if ($token === '?') {
            $position            = count($paramMap) + 1;
            $param               = ':param' . $position;
            $fragments[]         = substr($statement, $fragmentOffset, $tokenOffset - $fragmentOffset);
            $fragments[]         = $param;
            $paramMap[$position] = $param;
            $tokenOffset        += 1;
            $fragmentOffset      = $tokenOffset;

            return true;
        }

        $currentLiteralDelimiter = $token;
        ++$tokenOffset;

        return true;
    }

    /**
     * Finds closing quote
     *
     * @param string $statement               The SQL statement to parse
     * @param int    $tokenOffset             The offset to start searching from
     * @param string $currentLiteralDelimiter The delimiter of the current string literal
     *
     * @return bool Whether the token was found
     */
    private function findClosingQuote(
        string $statement,
        int &$tokenOffset,
        string &$currentLiteralDelimiter
    ): bool {
        $token = $this->findToken(
            $statement,
            $tokenOffset,
            '/' . preg_quote($currentLiteralDelimiter, '/') . '/'
        );

        if ($token === null) {
            return false;
        }

        $currentLiteralDelimiter = null;
        ++$tokenOffset;

        return true;
    }

    /**
     * Finds the token described by regex starting from the given offset. Updates the offset with the position
     * where the token was found.
     *
     * @param string $statement The SQL statement to parse
     * @param int    $offset    The offset to start searching from
     * @param string $regex     The regex containing token pattern
     *
     * @return string|null Token or NULL if not found
     */
    private function findToken(string $statement, int &$offset, string $regex): ?string
    {
        if (preg_match($regex, $statement, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $offset = $matches[0][1];

            return $matches[0][0];
        }

        return null;
    }
}

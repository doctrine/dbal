<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Closure;

use function array_change_key_case;
use function array_map;
use function array_reduce;
use function is_string;
use function rtrim;
use function strtolower;
use function strtoupper;

use const CASE_LOWER;
use const CASE_UPPER;

final class Converter
{
    public const CASE_LOWER = CASE_LOWER;
    public const CASE_UPPER = CASE_UPPER;

    private readonly Closure $convertNumeric;
    private readonly Closure $convertAssociative;
    private readonly Closure $convertOne;
    private readonly Closure $convertAllNumeric;
    private readonly Closure $convertAllAssociative;
    private readonly Closure $convertFirstColumn;
    private readonly Closure $convertColumnName;

    /**
     * @param bool                                   $convertEmptyStringToNull Whether each empty string should
     *                                                                         be converted to NULL
     * @param bool                                   $rightTrimString          Whether each string should right-trimmed
     * @param self::CASE_LOWER|self::CASE_UPPER|null $case                     Convert the case of the column names
     *                                                                         (one of {@see self::CASE_LOWER} and
     *                                                                         {@see self::CASE_UPPER})
     */
    public function __construct(bool $convertEmptyStringToNull, bool $rightTrimString, ?int $case)
    {
        $convertValue       = $this->createConvertValue($convertEmptyStringToNull, $rightTrimString);
        $convertNumeric     = $this->createConvertRow($convertValue, null);
        $convertAssociative = $this->createConvertRow($convertValue, $case);

        $this->convertNumeric     = $this->createConvert($convertNumeric);
        $this->convertAssociative = $this->createConvert($convertAssociative);
        $this->convertOne         = $this->createConvert($convertValue);

        $this->convertAllNumeric     = $this->createConvertAll($convertNumeric);
        $this->convertAllAssociative = $this->createConvertAll($convertAssociative);
        $this->convertFirstColumn    = $this->createConvertAll($convertValue);

        $this->convertColumnName = match ($case) {
            null => static fn (string $name) => $name,
            self::CASE_LOWER => strtolower(...),
            self::CASE_UPPER => strtoupper(...),
        };
    }

    /**
     * @param array<int,mixed>|false $row
     *
     * @return list<mixed>|false
     */
    public function convertNumeric(array|false $row): array|false
    {
        return ($this->convertNumeric)($row);
    }

    /**
     * @param array<string,mixed>|false $row
     *
     * @return array<string,mixed>|false
     */
    public function convertAssociative(array|false $row): array|false
    {
        return ($this->convertAssociative)($row);
    }

    public function convertOne(mixed $value): mixed
    {
        return ($this->convertOne)($value);
    }

    /**
     * @param list<list<mixed>> $data
     *
     * @return list<list<mixed>>
     */
    public function convertAllNumeric(array $data): array
    {
        return ($this->convertAllNumeric)($data);
    }

    /**
     * @param list<array<string,mixed>> $data
     *
     * @return list<array<string,mixed>>
     */
    public function convertAllAssociative(array $data): array
    {
        return ($this->convertAllAssociative)($data);
    }

    /**
     * @param list<mixed> $data
     *
     * @return list<mixed>
     */
    public function convertFirstColumn(array $data): array
    {
        return ($this->convertFirstColumn)($data);
    }

    public function convertColumnName(string $name): string
    {
        return ($this->convertColumnName)($name);
    }

    /**
     * @param T $value
     *
     * @return T
     *
     * @template T
     */
    private static function id(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param T $value
     *
     * @return T|null
     *
     * @template T
     */
    private static function convertEmptyStringToNull(mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param T $value
     *
     * @return T|string
     * @phpstan-return (T is string ? string : T)
     *
     * @template T
     */
    private static function rightTrimString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return rtrim($value);
    }

    /**
     * Creates a function that will convert each individual value retrieved from the database
     *
     * @param bool $convertEmptyStringToNull Whether each empty string should be converted to NULL
     * @param bool $rightTrimString          Whether each string should right-trimmed
     *
     * @return Closure|null The resulting function or NULL if no conversion is needed
     */
    private function createConvertValue(bool $convertEmptyStringToNull, bool $rightTrimString): ?Closure
    {
        $functions = [];

        if ($convertEmptyStringToNull) {
            $functions[] = self::convertEmptyStringToNull(...);
        }

        if ($rightTrimString) {
            $functions[] = self::rightTrimString(...);
        }

        return $this->compose(...$functions);
    }

    /**
     * Creates a function that will convert each array-row retrieved from the database
     *
     * @param Closure|null                           $function The function that will convert each value
     * @param self::CASE_LOWER|self::CASE_UPPER|null $case     Column name case
     *
     * @return Closure|null The resulting function or NULL if no conversion is needed
     */
    private function createConvertRow(?Closure $function, ?int $case): ?Closure
    {
        $functions = [];

        if ($function !== null) {
            $functions[] = $this->createMapper($function);
        }

        if ($case !== null) {
            $functions[] = static fn (array $row): array => array_change_key_case($row, $case);
        }

        return $this->compose(...$functions);
    }

    /**
     * Creates a function that will be applied to the return value of Statement::fetch*()
     * or an identity function if no conversion is needed
     *
     * @param Closure|null $function The function that will convert each tow
     */
    private function createConvert(?Closure $function): Closure
    {
        if ($function === null) {
            return self::id(...);
        }

        return /**
                * @param T $value
                *
                * @phpstan-return (T is false ? false : T)
                *
                * @template T
                */
            static function (mixed $value) use ($function): mixed {
                if ($value === false) {
                    return false;
                }

                return $function($value);
            };
    }

    /**
     * Creates a function that will be applied to the return value of Statement::fetchAll*()
     * or an identity function if no transformation is required
     *
     * @param Closure|null $function The function that will transform each value
     */
    private function createConvertAll(?Closure $function): Closure
    {
        if ($function === null) {
            return self::id(...);
        }

        return $this->createMapper($function);
    }

    /**
     * Creates a function that maps each value of the array using the given function
     *
     * @param Closure $function The function that maps each value of the array
     *
     * @return Closure(array<mixed>):array<mixed>
     */
    private function createMapper(Closure $function): Closure
    {
        return static fn (array $array): array => array_map($function, $array);
    }

    /**
     * Creates a composition of the given set of functions
     *
     * @param Closure(T):T ...$functions The functions to compose
     *
     * @return Closure(T):T|null
     *
     * @template T
     */
    private function compose(Closure ...$functions): ?Closure
    {
        return array_reduce($functions, static function (?Closure $carry, Closure $item): Closure {
            if ($carry === null) {
                return $item;
            }

            return /**
                    * @param T $value
                    *
                    * @return T
                    *
                    * @template T
                    */
                static fn (mixed $value): mixed => $item($carry($value));
        });
    }
}

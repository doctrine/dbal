<?php

declare(strict_types=1);

namespace Doctrine\StaticAnalysis\DBAL;

use Doctrine\DBAL\Portability\Converter;

/** @return false */
function convertNumericFalse(Converter $converter): bool
{
    return $converter->convertNumeric(false);
}

/** @return list<mixed> */
function convertNumericEmptyArray(Converter $converter): array
{
    return $converter->convertNumeric([]);
}

/** @return non-empty-list<mixed> */
function convertNumericNonEmptyArray(Converter $converter): array
{
    return $converter->convertNumeric(['foo']);
}

/** @return false */
function convertAssociativeFalse(Converter $converter): bool
{
    return $converter->convertAssociative(false);
}

/** @return array<string, mixed> */
function convertAssociativeEmptyArray(Converter $converter): array
{
    return $converter->convertAssociative([]);
}

/** @return non-empty-array<string, mixed> */
function convertAssociativeNonEmptyArray(Converter $converter): array
{
    return $converter->convertAssociative(['foo' => 'bar']);
}

/** @return list<list<mixed>> */
function convertAllNumericEmptyArray(Converter $converter): array
{
    return $converter->convertAllNumeric([[]]);
}

/** @return list<non-empty-list<mixed>> */
function convertAllNumericNonEmptyArray(Converter $converter): array
{
    return $converter->convertAllNumeric([['foo']]);
}


/** @return list<array<string, mixed>> */
function convertAllAssociativeEmptyArray(Converter $converter): array
{
    return $converter->convertAllAssociative([[]]);
}

/** @return list<non-empty-array<string, mixed>> */
function convertAllAssociativeNonEmptyArray(Converter $converter): array
{
    return $converter->convertAllAssociative([['foo' => 'bar']]);
}


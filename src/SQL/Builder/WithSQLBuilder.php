<?php

declare(strict_types=1);

namespace Doctrine\DBAL\SQL\Builder;

use Doctrine\DBAL\Query\CommonTableExpression;

use function array_merge;
use function count;
use function implode;

final class WithSQLBuilder
{
    public function buildSQL(CommonTableExpression $firstExpression, CommonTableExpression ...$otherExpressions): string
    {
        $cteParts = [];

        foreach (array_merge([$firstExpression], $otherExpressions) as $part) {
            $ctePart = [$part->name];
            if ($part->columns !== null && count($part->columns) > 0) {
                $ctePart[] = ' (' . implode(', ', $part->columns) . ')';
            }

            $ctePart[]  = ' AS (' . $part->query . ')';
            $cteParts[] = implode('', $ctePart);
        }

        return 'WITH ' . implode(', ', $cteParts);
    }
}

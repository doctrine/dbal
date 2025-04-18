<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

use function count;
use function sprintf;

/** @internal */
final readonly class CommonTableExpression
{
    /**
     * @param string[]|null $columns
     *
     * @throws QueryException
     */
    public function __construct(
        public string $name,
        public string|QueryBuilder $query,
        public ?array $columns,
    ) {
        if ($columns !== null && count($columns) === 0) {
            throw new QueryException(sprintf('Columns defined in CTE "%s" should not be an empty array.', $name));
        }
    }
}

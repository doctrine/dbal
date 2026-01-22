<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\Middleware\AbstractResultMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Override;

final class Result extends AbstractResultMiddleware
{
    /** @internal The result can be only instantiated by the portability connection or statement. */
    public function __construct(ResultInterface $result, private readonly Converter $converter)
    {
        parent::__construct($result);
    }

    #[Override]
    public function fetchNumeric(): array|false
    {
        return $this->converter->convertNumeric(
            parent::fetchNumeric(),
        );
    }

    #[Override]
    public function fetchAssociative(): array|false
    {
        return $this->converter->convertAssociative(
            parent::fetchAssociative(),
        );
    }

    #[Override]
    public function fetchOne(): mixed
    {
        return $this->converter->convertOne(
            parent::fetchOne(),
        );
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function fetchAllNumeric(): array
    {
        return $this->converter->convertAllNumeric(
            parent::fetchAllNumeric(),
        );
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function fetchAllAssociative(): array
    {
        return $this->converter->convertAllAssociative(
            parent::fetchAllAssociative(),
        );
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function fetchFirstColumn(): array
    {
        return $this->converter->convertFirstColumn(
            parent::fetchFirstColumn(),
        );
    }

    #[Override]
    public function getColumnName(int $index): string
    {
        return $this->converter->convertColumnName(
            parent::getColumnName($index),
        );
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\Middleware\AbstractResultMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;

final class Result extends AbstractResultMiddleware
{
    /** @internal The result can be only instantiated by the portability connection or statement. */
    public function __construct(ResultInterface $result, private readonly Converter $converter)
    {
        parent::__construct($result);
    }

    public function fetchNumeric(): array|false
    {
        return $this->converter->convertNumeric(
            parent::fetchNumeric(),
        );
    }

    public function fetchAssociative(): array|false
    {
        return $this->converter->convertAssociative(
            parent::fetchAssociative(),
        );
    }

    public function fetchOne(): mixed
    {
        return $this->converter->convertOne(
            parent::fetchOne(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        return $this->converter->convertAllNumeric(
            parent::fetchAllNumeric(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        return $this->converter->convertAllAssociative(
            parent::fetchAllAssociative(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        return $this->converter->convertFirstColumn(
            parent::fetchFirstColumn(),
        );
    }
}

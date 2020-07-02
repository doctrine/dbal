<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\API\OCI;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\OCI\ExceptionConverter;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tests\Driver\API\ExceptionConverterTest as BaseExceptionConverterTest;

final class ExceptionConverterTest extends BaseExceptionConverterTest
{
    protected function createConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }

    /**
     * {@inheritDoc}
     */
    protected static function getExceptionConversionData(): array
    {
        return [
            ConnectionException::class => [
                [1017],
                [12545],
            ],
            ForeignKeyConstraintViolationException::class => [
                [2292],
            ],
            InvalidFieldNameException::class => [
                [904],
            ],
            NonUniqueFieldNameException::class => [
                [918],
                [960],
            ],
            NotNullConstraintViolationException::class => [
                [1400],
            ],
            SyntaxErrorException::class => [
                [923],
            ],
            TableExistsException::class => [
                [955],
            ],
            TableNotFoundException::class => [
                [942],
            ],
            UniqueConstraintViolationException::class => [
                [1],
                [2299],
                [38911],
            ],
        ];
    }
}

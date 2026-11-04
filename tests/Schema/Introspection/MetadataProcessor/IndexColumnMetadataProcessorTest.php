<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Introspection\MetadataProcessor;

use Doctrine\DBAL\Schema\Index\IndexMethod;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Introspection\MetadataProcessor\IndexColumnMetadataProcessor;
use Doctrine\DBAL\Schema\Metadata\IndexColumnMetadataRow;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class IndexColumnMetadataProcessorTest extends TestCase
{
    #[TestWith([null])]
    #[TestWith([IndexMethod::GIN])]
    #[TestWith([IndexMethod::GIST])]
    public function testPropagatesMethod(?IndexMethod $method): void
    {
        $processor = new IndexColumnMetadataProcessor();

        $row = new IndexColumnMetadataRow(
            schemaName: null,
            tableName: 'article',
            indexName: 'idx_document',
            type: IndexType::REGULAR,
            isClustered: false,
            predicate: null,
            columnName: 'document',
            columnLength: null,
            method: $method,
        );

        $editor = $processor->initializeEditor($row);
        $processor->applyRow($editor, $row);

        self::assertSame($method, $editor->create()->getMethod());
    }
}

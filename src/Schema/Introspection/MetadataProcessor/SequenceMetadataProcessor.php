<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Introspection\MetadataProcessor;

use Doctrine\DBAL\Schema\Metadata\SequenceMetadataRow;
use Doctrine\DBAL\Schema\Sequence;

/**
 * Converts {@see SequenceMetadataRow} into a {@see Sequence}.
 *
 * @internal Should be used only by {@link IntrospectingSchemaProvider}.
 */
final readonly class SequenceMetadataProcessor
{
    public function createObject(SequenceMetadataRow $row): Sequence
    {
        return Sequence::editor()
            ->setQuotedName($row->getSequenceName(), $row->getSchemaName())
            ->setAllocationSize($row->getAllocationSize())
            ->setInitialValue($row->getInitialValue())
            ->setCacheSize($row->getCacheSize())
            ->create();
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Override;

class SequenceTest extends FunctionalTestCase
{
    #[Override]
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSequences()) {
            return;
        }

        self::markTestSkipped('The platform does not support sequences.');
    }

    public function testNextValue(): void
    {
        $this->connection->createSchemaManager()->createSequence(
            Sequence::editor()
                ->setUnquotedName('next_value_test_seq')
                ->create(),
        );

        $sql = $this->connection->getDatabasePlatform()
            ->getSequenceNextValSQL('next_value_test_seq');

        $first  = (int) $this->connection->fetchOne($sql);
        $second = (int) $this->connection->fetchOne($sql);

        self::assertGreaterThan($first, $second);
    }
}

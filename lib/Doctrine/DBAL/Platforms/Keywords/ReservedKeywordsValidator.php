<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Keywords;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Visitor\Visitor;
use function implode;
use function str_replace;

class ReservedKeywordsValidator implements Visitor
{
    /** @var KeywordList[] */
    private $keywordLists = [];

    /** @var string[] */
    private $violations = [];

    /**
     * @param KeywordList[] $keywordLists
     */
    public function __construct(array $keywordLists)
    {
        $this->keywordLists = $keywordLists;
    }

    /**
     * @return string[]
     */
    public function getViolations() : array
    {
        return $this->violations;
    }

    /**
     * @return string[]
     */
    private function isReservedWord(string $word) : array
    {
        if ($word[0] === '`') {
            $word = str_replace('`', '', $word);
        }

        $keywordLists = [];
        foreach ($this->keywordLists as $keywordList) {
            if (! $keywordList->isKeyword($word)) {
                continue;
            }

            $keywordLists[] = $keywordList->getName();
        }

        return $keywordLists;
    }

    /**
     * @param string[] $violatedPlatforms
     */
    private function addViolation(string $asset, array $violatedPlatforms) : void
    {
        if (! $violatedPlatforms) {
            return;
        }

        $this->violations[] = $asset . ' keyword violations: ' . implode(', ', $violatedPlatforms);
    }

    /**
     * {@inheritdoc}
     */
    public function acceptColumn(Table $table, Column $column) : void
    {
        $this->addViolation(
            'Table ' . $table->getName() . ' column ' . $column->getName(),
            $this->isReservedWord($column->getName())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptIndex(Table $table, Index $index) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSchema(Schema $schema) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSequence(Sequence $sequence) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptTable(Table $table) : void
    {
        $this->addViolation(
            'Table ' . $table->getName(),
            $this->isReservedWord($table->getName())
        );
    }
}

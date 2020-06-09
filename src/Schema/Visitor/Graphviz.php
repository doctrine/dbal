<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

use function current;
use function file_put_contents;
use function in_array;
use function strtolower;

/**
 * Create a Graphviz output of a Schema.
 */
class Graphviz extends AbstractVisitor
{
    /** @var string */
    private $output = '';

    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint): void
    {
        $this->output .= $this->createNodeRelation(
            $fkConstraint->getLocalTableName() . ':col' . current($fkConstraint->getLocalColumns()) . ':se',
            $fkConstraint->getForeignTableName() . ':col' . current($fkConstraint->getForeignColumns()) . ':se',
            [
                'dir'       => 'back',
                'arrowtail' => 'dot',
                'arrowhead' => 'normal',
            ]
        );
    }

    public function acceptSchema(Schema $schema): void
    {
        $this->output  = 'digraph "' . $schema->getName() . '" {' . "\n";
        $this->output .= 'splines = true;' . "\n";
        $this->output .= 'overlap = false;' . "\n";
        $this->output .= 'outputorder=edgesfirst;' . "\n";
        $this->output .= 'mindist = 0.6;' . "\n";
        $this->output .= 'sep = .2;' . "\n";
    }

    public function acceptTable(Table $table): void
    {
        $this->output .= $this->createNode(
            $table->getName(),
            [
                'label' => $this->createTableLabel($table),
                'shape' => 'plaintext',
            ]
        );
    }

    private function createTableLabel(Table $table): string
    {
        // Start the table
        $label = '<<TABLE CELLSPACING="0" BORDER="1" ALIGN="LEFT">';

        // The title
        $label .= '<TR><TD BORDER="1" COLSPAN="3" ALIGN="CENTER" BGCOLOR="#fcaf3e"><FONT COLOR="#2e3436" FACE="Helvetica" POINT-SIZE="12">' . $table->getName() . '</FONT></TD></TR>';

        // The attributes block
        foreach ($table->getColumns() as $column) {
            $columnLabel = $column->getName();

            $label .= '<TR>';
            $label .= '<TD BORDER="0" ALIGN="LEFT" BGCOLOR="#eeeeec">';
            $label .= '<FONT COLOR="#2e3436" FACE="Helvetica" POINT-SIZE="12">' . $columnLabel . '</FONT>';
            $label .= '</TD><TD BORDER="0" ALIGN="LEFT" BGCOLOR="#eeeeec"><FONT COLOR="#2e3436" FACE="Helvetica" POINT-SIZE="10">' . strtolower($column->getType()->getName()) . '</FONT></TD>';
            $label .= '<TD BORDER="0" ALIGN="RIGHT" BGCOLOR="#eeeeec" PORT="col' . $column->getName() . '">';

            $primaryKey = $table->getPrimaryKey();

            if ($primaryKey !== null && in_array($column->getName(), $primaryKey->getColumns(), true)) {
                $label .= "\xe2\x9c\xb7";
            }

            $label .= '</TD></TR>';
        }

        // End the table
        $label .= '</TABLE>>';

        return $label;
    }

    /**
     * @param array<string, string> $options
     */
    private function createNode(string $name, array $options): string
    {
        $node = $name . ' [';
        foreach ($options as $key => $value) {
            $node .= $key . '=' . $value . ' ';
        }

        $node .= "]\n";

        return $node;
    }

    /**
     * @param array<string, string> $options
     */
    private function createNodeRelation(string $node1, string $node2, array $options): string
    {
        $relation = $node1 . ' -> ' . $node2 . ' [';
        foreach ($options as $key => $value) {
            $relation .= $key . '=' . $value . ' ';
        }

        $relation .= "]\n";

        return $relation;
    }

    /**
     * Get Graphviz Output
     */
    public function getOutput(): string
    {
        return $this->output . '}';
    }

    /**
     * Writes dot language output to a file. This should usually be a *.dot file.
     *
     * You have to convert the output into a viewable format. For example use "neato" on linux systems
     * and execute:
     *
     *  neato -Tpng -o er.png er.dot
     */
    public function write(string $filename): void
    {
        file_put_contents($filename, $this->getOutput());
    }
}

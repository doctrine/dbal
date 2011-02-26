<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */


namespace Doctrine\DBAL\Platforms\Keywords;

use Doctrine\DBAL\Schema\Visitor\Visitor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Index;

class ReservedKeywordsValidator implements Visitor
{
    /**
     * @var KeywordList[]
     */
    private $keywordLists = array();
    
    /**
     * @var array
     */
    private $violations = array();
    
    public function __construct(array $keywordLists)
    {
        $this->keywordLists = $keywordLists;
    }
    
    public function getViolations()
    {
        return $this->violations;
    }
    
    /**
     * @param string $word
     * @return array
     */
    private function isReservedWord($word)
    {
        if ($word[0] == "`") {
            $word = str_replace('`', '', $word);
        }
        
        $keywordLists = array();
        foreach ($this->keywordLists AS $keywordList) {
            if ($keywordList->isKeyword($word)) {
                $keywordLists[] = $keywordList->getName();
            }
        }
        return $keywordLists;
    }
    
    private function addViolation($asset, $violatedPlatforms)
    {
        if (!$violatedPlatforms) {
            return;
        }
        
        $this->violations[] = $asset . ' keyword violations: ' . implode(', ', $violatedPlatforms);
    }
    
    public function acceptColumn(Table $table, Column $column)
    {
        $this->addViolation(
            'Table ' . $table->getName() . ' column ' . $column->getName(),
            $this->isReservedWord($column->getName())
        );
    }

    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint)
    {
        
    }

    public function acceptIndex(Table $table, Index $index)
    {

    }

    public function acceptSchema(Schema $schema)
    {
        
    }

    public function acceptSequence(Sequence $sequence)
    {
        
    }

    public function acceptTable(Table $table)
    {
        $this->addViolation(
            'Table ' . $table->getName(),
            $this->isReservedWord($table->getName())
        );
    }
}
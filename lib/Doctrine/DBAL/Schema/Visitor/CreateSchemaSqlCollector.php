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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;

class CreateSchemaSqlCollector extends AbstractVisitor
{
    /**
     * @var array
     */
    private $createNamespaceQueries = array();

    /**
     * @var array
     */
    private $createTableQueries = array();

    /**
     * @var array
     */
    private $createSequenceQueries = array();

    /**
     * @var array
     */
    private $createFkConstraintQueries = array();

    /**
     *
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $platform = null;

    /**
     * @param AbstractPlatform $platform
     */
    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    /**
     * {@inheritdoc}
     */
    public function acceptNamespace(string $namespaceName): void
    {
        if ($this->platform->supportsSchemas()) {
            $this->createNamespaceQueries[] = $this->platform->getCreateSchemaSQL($namespaceName);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function acceptTable(Table $table): void
    {
        $this->createTableQueries = array_merge($this->createTableQueries, (array) $this->platform->getCreateTableSQL($table));
    }

    /**
     * {@inheritdoc}
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint): void
    {
        if ($this->platform->supportsForeignKeyConstraints()) {
            $this->createFkConstraintQueries[] = $this->platform->getCreateForeignKeySQL($fkConstraint, $localTable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSequence(Sequence $sequence): void
    {
        $this->createSequenceQueries[] = $this->platform->getCreateSequenceSQL($sequence);
    }

    /**
     * @return void
     */
    public function resetQueries(): void
    {
        $this->createNamespaceQueries = array();
        $this->createTableQueries = array();
        $this->createSequenceQueries = array();
        $this->createFkConstraintQueries = array();
    }

    /**
     * Gets all queries collected so far.
     *
     * @return array
     */
    public function getQueries(): array
    {
        return array_merge(
            $this->createNamespaceQueries,
            $this->createTableQueries,
            $this->createSequenceQueries,
            $this->createFkConstraintQueries
        );
    }
}

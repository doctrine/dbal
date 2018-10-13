<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class DatabasePlatformMock extends AbstractPlatform
{
    /** @var string */
    private $sequenceNextValSql = '';

    /** @var bool */
    private $prefersIdentityColumns = true;

    /** @var bool */
    private $prefersSequences = false;

    public function prefersIdentityColumns()
    {
        return $this->prefersIdentityColumns;
    }

    public function prefersSequences()
    {
        return $this->prefersSequences;
    }

    public function getSequenceNextValSQL($sequenceName)
    {
        return $this->sequenceNextValSql;
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getVarcharTypeDeclarationSQL(array $field)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
    }

    /* MOCK API */

    /**
     * @param bool $prefersIdentityColumns
     */
    public function setPrefersIdentityColumns($prefersIdentityColumns)
    {
        $this->prefersIdentityColumns = $prefersIdentityColumns;
    }

    public function setPrefersSequences($bool)
    {
        $this->prefersSequences = $bool;
    }

    public function setSequenceNextValSql($sql)
    {
        $this->sequenceNextValSql = $sql;
    }

    public function getName()
    {
        return 'mock';
    }
    protected function initializeDoctrineTypeMappings()
    {
    }
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        throw DBALException::notSupported(__METHOD__);
    }
}

<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class DatabasePlatformMock extends AbstractPlatform
{
    /** @var string */
    private $_sequenceNextValSql = '';

    /** @var bool */
    private $_prefersIdentityColumns = true;

    /** @var bool */
    private $_prefersSequences = false;

    public function prefersIdentityColumns()
    {
        return $this->_prefersIdentityColumns;
    }

    public function prefersSequences()
    {
        return $this->_prefersSequences;
    }

    public function getSequenceNextValSQL($sequenceName)
    {
        return $this->_sequenceNextValSql;
    }

    public function getBooleanTypeDeclarationSQL(array $field)
    {
    }

    public function getIntegerTypeDeclarationSQL(array $field)
    {
    }

    public function getBigIntTypeDeclarationSQL(array $field)
    {
    }

    public function getSmallIntTypeDeclarationSQL(array $field)
    {
    }

    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
    }

    public function getVarcharTypeDeclarationSQL(array $field)
    {
    }

    public function getClobTypeDeclarationSQL(array $field)
    {
    }

    /* MOCK API */

    public function setPrefersIdentityColumns($bool)
    {
        $this->_prefersIdentityColumns = $bool;
    }

    public function setPrefersSequences($bool)
    {
        $this->_prefersSequences = $bool;
    }

    public function setSequenceNextValSql($sql)
    {
        $this->_sequenceNextValSql = $sql;
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
     * Gets the SQL Snippet used to declare a BLOB column type.
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        throw DBALException::notSupported(__METHOD__);
    }
}

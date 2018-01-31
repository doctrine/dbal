<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\DBALException;

class DatabasePlatformMock extends \Doctrine\DBAL\Platforms\AbstractPlatform
{
    private $sequenceNextValSql = "";
    private $prefersIdentityColumns = true;
    private $prefersSequences = false;

    /**
     * @override
     */
    public function prefersIdentityColumns()
    {
        return $this->prefersIdentityColumns;
    }

    /**
     * @override
     */
    public function prefersSequences()
    {
        return $this->prefersSequences;
    }

    /** @override */
    public function getSequenceNextValSQL($sequenceName)
    {
        return $this->sequenceNextValSql;
    }

    /** @override */
    public function getBooleanTypeDeclarationSQL(array $field) {}

    /** @override */
    public function getIntegerTypeDeclarationSQL(array $field) {}

    /** @override */
    public function getBigIntTypeDeclarationSQL(array $field) {}

    /** @override */
    public function getSmallIntTypeDeclarationSQL(array $field) {}

    /** @override */
    protected function getCommonIntegerTypeDeclarationSQL(array $columnDef) {}

    /** @override */
    public function getVarcharTypeDeclarationSQL(array $field) {}

    /** @override */
    public function getClobTypeDeclarationSQL(array $field) {}

    /* MOCK API */

    public function setPrefersIdentityColumns($bool)
    {
        $this->prefersIdentityColumns = $bool;
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
    protected function initializeDoctrineTypeMappings() {
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
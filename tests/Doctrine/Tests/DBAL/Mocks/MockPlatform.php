<?php

namespace Doctrine\Tests\DBAL\Mocks;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class MockPlatform extends AbstractPlatform
{
    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $columnDef)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $columnDef)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $columnDef)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $columnDef)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getVarcharTypeDeclarationSQL(array $field)
    {
        return 'DUMMYVARCHAR()';
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'DUMMYCLOB';
    }

    /**
     * {@inheritdoc}
     */
    public function getJsonTypeDeclarationSQL(array $field)
    {
        return 'DUMMYJSON';
    }

    /**
     * {@inheritdoc}
     */
    public function getBinaryTypeDeclarationSQL(array $field)
    {
        return 'DUMMYBINARY';
    }

    public function getVarcharDefaultLength()
    {
        return 255;
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
}

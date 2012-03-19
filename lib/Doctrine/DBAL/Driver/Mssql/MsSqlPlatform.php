<?php

namespace Doctrine\DBAL\Driver\Mssql;

class MsSqlPlatform extends \Doctrine\DBAL\Platforms\SQLServer2008Platform
{
    /**
     * @override
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s';
    }

    /**
    * Adds an adapter-specific LIMIT clause to the SELECT statement.
    *
    * @param string $query
    * @param mixed $limit
    * @param mixed $offset
    * @link http://lists.bestpractical.com/pipermail/rt-devel/2005-June/007339.html
    * @return string
    */
    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        return str_replace('"doctrine_rownum"', 'doctrine_rownum', parent::doModifyLimitQuery($query, $limit, $offset));
    }
}

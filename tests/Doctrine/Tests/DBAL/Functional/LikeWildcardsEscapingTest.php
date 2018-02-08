<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\Tests\DbalFunctionalTestCase;

final class LikeWildcardsEscapingTest extends DbalFunctionalTestCase
{
    public function testFetchLikeExpressionResult() : void
    {
        $string           = '_25% off_ your next purchase \o/';
        $escapeChar       = '!';
        $databasePlatform = $this->_conn->getDatabasePlatform();
        $stmt             = $this->_conn->prepare(sprintf(
            "SELECT (CASE WHEN '%s' LIKE '%s' ESCAPE '%s' THEN 1 ELSE 0 END)" .
                ($databasePlatform instanceof OraclePlatform ? ' FROM dual' : ''),
            $string,
            $databasePlatform->escapeStringForLike($string, $escapeChar),
            $escapeChar
        ));
        $stmt->execute();
        $this->assertTrue((bool) $stmt->fetchColumn());
    }
}

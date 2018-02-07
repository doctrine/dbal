<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Tests\DbalFunctionalTestCase;

final class LikeWildcardsEscapingTest extends DbalFunctionalTestCase
{
    public function testFetchLikeExpressionResult() : void
    {
        $string     = '_25% off_ your next purchase \o/';
        $escapeChar = '!';
        $stmt       = $this->_conn->prepare(sprintf(
            "SELECT '%s' LIKE '%s' ESCAPE '%s' as it_matches",
            $string,
            $this->_conn->getDatabasePlatform()->escapeStringForLike($string, $escapeChar),
            $escapeChar
        ));
        $stmt->execute();
        $this->assertTrue((bool) $stmt->fetch()['it_matches']);
    }
}

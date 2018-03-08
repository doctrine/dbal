<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Tests\DbalFunctionalTestCase;
use function sprintf;
use function str_replace;

final class LikeWildcardsEscapingTest extends DbalFunctionalTestCase
{
    public function testFetchLikeExpressionResult() : void
    {
        $string           = '_25% off_ your next purchase \o/ [$̲̅(̲̅5̲̅)̲̅$̲̅] (^̮^)';
        $escapeChar       = '!';
        $databasePlatform = $this->_conn->getDatabasePlatform();
        $stmt             = $this->_conn->prepare(str_replace(
            '1',
            sprintf(
                "(CASE WHEN '%s' LIKE '%s' ESCAPE '%s' THEN 1 ELSE 0 END)",
                $string,
                $databasePlatform->escapeStringForLike($string, $escapeChar),
                $escapeChar
            ),
            $databasePlatform->getDummySelectSQL()
        ));
        $stmt->execute();
        $this->assertTrue((bool) $stmt->fetchColumn());
    }
}

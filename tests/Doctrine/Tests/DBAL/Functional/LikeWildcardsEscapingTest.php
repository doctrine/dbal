<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Tests\DBAL\FunctionalTestCase;
use function sprintf;

final class LikeWildcardsEscapingTest extends FunctionalTestCase
{
    public function testFetchLikeExpressionResult() : void
    {
        $string           = '_25% off_ your next purchase \o/ [$̲̅(̲̅5̲̅)̲̅$̲̅] (^̮^)';
        $escapeChar       = '!';
        $databasePlatform = $this->connection->getDatabasePlatform();
        $stmt             = $this->connection->prepare(
            $databasePlatform->getDummySelectSQL(
                sprintf(
                    "(CASE WHEN '%s' LIKE '%s' ESCAPE '%s' THEN 1 ELSE 0 END)",
                    $string,
                    $databasePlatform->escapeStringForLike($string, $escapeChar),
                    $escapeChar
                )
            )
        );
        $stmt->execute();
        $this->assertTrue((bool) $stmt->fetchColumn());
    }
}

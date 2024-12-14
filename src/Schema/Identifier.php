<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\GenericName;
use Doctrine\DBAL\Schema\Name\Parser\GenericNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;

/**
 * An abstraction class for an asset identifier.
 *
 * Wraps identifier names like column names in indexes / foreign keys
 * in an abstract class for proper quotation capabilities.
 *
 * @internal
 *
 * @extends AbstractNamedObject<GenericName>
 */
class Identifier extends AbstractNamedObject
{
    protected function getNameParser(): GenericNameParser
    {
        return Parsers::getGenericNameParser();
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

final class InvalidState extends LogicException implements SchemaException
{
}

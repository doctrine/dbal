<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

/**
 * The kind of {@see DerivedObject}.
 */
enum DerivedObjectKind
{
    case UniqueIndex;

    case RegularIndex;

    case UniqueConstraint;
}

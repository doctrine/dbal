<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

enum ObjectOrigin
{
    /** The application's schema contains the object. */
    case DECLARED;

    /** The database reports the object because of a declared object. */
    case DERIVED;
}

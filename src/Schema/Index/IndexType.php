<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Index;

enum IndexType
{
    case REGULAR;
    case UNIQUE;
    case FULLTEXT;
    case SPATIAL;
}

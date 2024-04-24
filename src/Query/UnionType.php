<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

enum UnionType
{
    case ALL;
    case DISTINCT;
}

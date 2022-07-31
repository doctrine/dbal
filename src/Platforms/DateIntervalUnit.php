<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

enum DateIntervalUnit: string
{
    case SECOND  = 'SECOND';
    case MINUTE  = 'MINUTE';
    case HOUR    = 'HOUR';
    case DAY     = 'DAY';
    case WEEK    = 'WEEK';
    case MONTH   = 'MONTH';
    case QUARTER = 'QUARTER';
    case YEAR    = 'YEAR';
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

enum LogMessage: string
{
    case BEGIN_TRANSACTION = 'begin_transaction';
    case COMMIT            = 'commit_transaction';
    case CONNECT           = 'connect';
    case DISCONNECT        = 'disconnect';
    case EXECUTE           = 'execute';
    case QUERY             = 'query';
    case ROLL_BACK         = 'roll_back_transaction';
    case STATEMENT         = 'statement';
}

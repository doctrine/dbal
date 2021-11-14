<?php

namespace Doctrine\DBAL\ForwardCompatibility;

use Doctrine\DBAL;

interface DriverStatement extends DBAL\Driver\Statement, DBAL\Result
{
}

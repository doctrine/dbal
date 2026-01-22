<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Initializer;

use Doctrine\DBAL\Driver\Mysqli\Exception\InvalidOption;
use Doctrine\DBAL\Driver\Mysqli\Initializer;
use mysqli;
use Override;

use function mysqli_options;

final readonly class Options implements Initializer
{
    /** @param array<int,mixed> $options */
    public function __construct(private array $options)
    {
    }

    #[Override]
    public function initialize(mysqli $connection): void
    {
        foreach ($this->options as $option => $value) {
            // mysqli::options() doesn't respect error reporting configuration and reports failure as false return value
            // See: https://github.com/php/php-src/issues/20968
            if (! mysqli_options($connection, $option, $value)) {
                throw InvalidOption::fromOption($option, $value);
            }
        }
    }
}

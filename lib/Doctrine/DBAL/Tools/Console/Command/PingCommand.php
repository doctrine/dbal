<?php

namespace Doctrine\DBAL\Tools\Console\Command;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function is_numeric;
use function sleep;
use function sprintf;

class PingCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('dbal:ping')
            ->setDescription('Check db is available')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max number of pings to try', '1')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Length of time (seconds) to sleep between pings', '1')
            ->setHelp(<<<EOT
Connects to the database to check if it is accessible.

The exit code will be non-zero when the connection fails.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $limit = $input->getOption('limit');
        if (! is_numeric($limit) || $limit < 0) {
            throw new RuntimeException('Option "limit" must contain a positive integer value');
        }
        $sleep = $input->getOption('sleep');
        if (! is_numeric($sleep) || $sleep < 0) {
            throw new RuntimeException('Option "sleep" must contain a positive integer value');
        }

        return $this->waitForPing($this->getHelper('db')->getConnection(), (int) $limit, (int) $sleep, $output);
    }

    /**
     * @return int > 0 for error
     */
    private function waitForPing(Connection $conn, int $limit, int $sleep, OutputInterface $output) : int
    {
        while (true) {
            $last = $this->ping($conn, $output);
            if ($last === 0 || --$limit <= 0) {
                break;
            }
            sleep($sleep);
        }

        return $last;
    }

    /**
     * @return int > 0 for error
     */
    private function ping(Connection $conn, OutputInterface $output) : int
    {
        try {
            if ($conn->ping()) {
                return 0;
            }

            $output->writeln('Ping failed');

            return 1;
        } catch (Throwable $e) {
            $output->writeln(sprintf('Ping failed: <error>%s</error>', $e->getMessage()));

            return 2;
        }
    }
}

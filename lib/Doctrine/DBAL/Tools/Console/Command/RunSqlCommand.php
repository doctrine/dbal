<?php

namespace Doctrine\DBAL\Tools\Console\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Console\ConnectionProvider;
use Doctrine\DBAL\Tools\Dumper;
use Doctrine\Deprecations\Deprecation;
use Exception;
use LogicException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function is_numeric;
use function is_string;
use function stripos;

/**
 * Task for executing arbitrary SQL that can come from a file or directly from
 * the command line.
 */
class RunSqlCommand extends Command
{
    /** @var ConnectionProvider|null */
    private $connectionProvider;

    public function __construct(?ConnectionProvider $connectionProvider = null)
    {
        parent::__construct();
        $this->connectionProvider = $connectionProvider;
        if ($connectionProvider !== null) {
            return;
        }

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/3956',
            'Not passing a connection provider as the first constructor argument is deprecated'
        );
    }

    /** @return void */
    protected function configure()
    {
        $this
        ->setName('dbal:run-sql')
        ->setDescription('Executes arbitrary SQL directly from the command line.')
        ->setDefinition([
            new InputOption('connection', null, InputOption::VALUE_REQUIRED, 'The named database connection'),
            new InputArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.'),
            new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Dumping depth of result set.', '7'),
            new InputOption('force-fetch', null, InputOption::VALUE_NONE, 'Forces fetching the result.'),
        ])
        ->setHelp(<<<EOT
The <info>%command.name%</info> command executes the given SQL query and
outputs the results:

<info>php %command.full_name% "SELECT * FROM users"</info>
EOT
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $conn = $this->getConnection($input);

        $sql = $input->getArgument('sql');

        if ($sql === null) {
            throw new RuntimeException("Argument 'SQL' is required in order to execute this command correctly.");
        }

        assert(is_string($sql));

        $depth = $input->getOption('depth');

        if (! is_numeric($depth)) {
            throw new LogicException("Option 'depth' must contains an integer value");
        }

        if (stripos($sql, 'select') === 0 || $input->getOption('force-fetch')) {
            $resultSet = $conn->fetchAllAssociative($sql);
        } else {
            $resultSet = $conn->executeStatement($sql);
        }

        $output->write(Dumper::dump($resultSet, (int) $depth));

        return 0;
    }

    private function getConnection(InputInterface $input): Connection
    {
        $connectionName = $input->getOption('connection');
        assert(is_string($connectionName) || $connectionName === null);

        if ($this->connectionProvider === null) {
            if ($connectionName !== null) {
                throw new Exception('Specifying a connection is only supported when a ConnectionProvider is used.');
            }

            return $this->getHelper('db')->getConnection();
        }

        if ($connectionName !== null) {
            return $this->connectionProvider->getConnection($connectionName);
        }

        return $this->connectionProvider->getDefaultConnection();
    }
}

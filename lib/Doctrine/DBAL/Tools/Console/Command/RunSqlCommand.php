<?php

namespace Doctrine\DBAL\Tools\Console\Command;

use Doctrine\DBAL\Tools\Dumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use function is_numeric;
use function stripos;

/**
 * Task for executing arbitrary SQL that can come from a file or directly from
 * the command line.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 */
class RunSqlCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
        ->setName('dbal:run-sql')
        ->setDescription('Executes arbitrary SQL directly from the command line.')
        ->setDefinition([
            new InputArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.'),
            new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Dumping depth of result set.', 7),
            new InputOption('force-fetch', null, InputOption::VALUE_NONE, 'Forces fetching the result.'),
        ])
        ->setHelp(<<<EOT
Executes arbitrary SQL directly from the command line.
EOT
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $conn = $this->getHelper('db')->getConnection();

        if (($sql = $input->getArgument('sql')) === null) {
            throw new \RuntimeException("Argument 'SQL' is required in order to execute this command correctly.");
        }

        $depth = $input->getOption('depth');

        if ( ! is_numeric($depth)) {
            throw new \LogicException("Option 'depth' must contains an integer value");
        }

        if (stripos($sql, 'select') === 0 || $input->getOption('force-fetch')) {
            $resultSet = $conn->fetchAll($sql);
        } else {
            $resultSet = $conn->executeUpdate($sql);
        }

        $output->write(Dumper::dump($resultSet, (int) $depth));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tools\Console\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Keywords\DB2Keywords;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MariaDBKeywords;
use Doctrine\DBAL\Platforms\Keywords\MySQL80Keywords;
use Doctrine\DBAL\Platforms\Keywords\MySQLKeywords;
use Doctrine\DBAL\Platforms\Keywords\OracleKeywords;
use Doctrine\DBAL\Platforms\Keywords\PostgreSQLKeywords;
use Doctrine\DBAL\Platforms\Keywords\ReservedKeywordsValidator;
use Doctrine\DBAL\Platforms\Keywords\SQLiteKeywords;
use Doctrine\DBAL\Platforms\Keywords\SQLServerKeywords;
use Doctrine\DBAL\Tools\Console\ConnectionProvider;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function assert;
use function count;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

class ReservedWordsCommand extends Command
{
    /** @var array<string,KeywordList> */
    private array $keywordLists;

    private ConnectionProvider $connectionProvider;

    public function __construct(ConnectionProvider $connectionProvider)
    {
        parent::__construct();
        $this->connectionProvider = $connectionProvider;

        $this->keywordLists = [
            'db2'        => new DB2Keywords(),
            'mariadb'    => new MariaDBKeywords(),
            'mysql'      => new MySQLKeywords(),
            'mysql80'    => new MySQL80Keywords(),
            'oracle'     => new OracleKeywords(),
            'pgsql'      => new PostgreSQLKeywords(),
            'sqlite'     => new SQLiteKeywords(),
            'sqlserver'  => new SQLServerKeywords(),
        ];
    }

    /**
     * Add or replace a keyword list.
     */
    public function setKeywordList(string $name, KeywordList $keywordList): void
    {
        $this->keywordLists[$name] = $keywordList;
    }

    protected function configure(): void
    {
        $this
        ->setName('dbal:reserved-words')
        ->setDescription('Checks if the current database contains identifiers that are reserved.')
        ->setDefinition([
            new InputOption('connection', null, InputOption::VALUE_REQUIRED, 'The named database connection'),
            new InputOption(
                'list',
                'l',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Keyword-List name.'
            ),
        ])
        ->setHelp(<<<EOT
Checks if the current database contains tables and columns
with names that are identifiers in this dialect or in other SQL dialects.

By default all supported platform keywords are checked:

    <info>%command.full_name%</info>

If you want to check against specific dialects you can
pass them to the command:

    <info>%command.full_name% -l mysql -l pgsql</info>

The following keyword lists are currently shipped with Doctrine:

    * db2
    * mariadb
    * mysql
    * mysql80
    * oracle
    * pgsql
    * sqlite
    * sqlserver
EOT
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->getConnection($input);

        $keywordLists = $input->getOption('list');

        if (is_string($keywordLists)) {
            $keywordLists = [$keywordLists];
        } elseif (! is_array($keywordLists)) {
            $keywordLists = [];
        }

        if (count($keywordLists) === 0) {
            $keywordLists = array_keys($this->keywordLists);
        }

        $keywords = [];
        foreach ($keywordLists as $keywordList) {
            if (! isset($this->keywordLists[$keywordList])) {
                throw new InvalidArgumentException(sprintf(
                    'There exists no keyword list with name "%s". Known lists: %s',
                    $keywordList,
                    implode(', ', array_keys($this->keywordLists))
                ));
            }

            $keywords[] = $this->keywordLists[$keywordList];
        }

        $output->write(
            'Checking keyword violations for <comment>' . implode(', ', $keywordLists) . '</comment>...',
            true
        );

        $schema  = $conn->createSchemaManager()->createSchema();
        $visitor = new ReservedKeywordsValidator($keywords);
        $schema->visit($visitor);

        $violations = $visitor->getViolations();
        if (count($violations) !== 0) {
            $output->write(
                'There are <error>' . count($violations) . '</error> reserved keyword violations'
                . ' in your database schema:',
                true
            );

            foreach ($violations as $violation) {
                $output->write('  - ' . $violation, true);
            }

            return 1;
        }

        $output->write('No reserved keywords violations have been found!', true);

        return 0;
    }

    private function getConnection(InputInterface $input): Connection
    {
        $connectionName = $input->getOption('connection');
        assert(is_string($connectionName) || $connectionName === null);

        if ($connectionName !== null) {
            return $this->connectionProvider->getConnection($connectionName);
        }

        return $this->connectionProvider->getDefaultConnection();
    }
}

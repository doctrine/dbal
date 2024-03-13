<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDB1060Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Query\From;
use Doctrine\DBAL\Query\Join;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Query\QueryType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class QueryBuilderTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('for_update');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('for_update', ['id' => 1]);
        $this->connection->insert('for_update', ['id' => 2]);
    }

    protected function tearDown(): void
    {
        if (! $this->connection->isTransactionActive()) {
            return;
        }

        $this->connection->rollBack();
    }

    public function testForUpdateOrdinary(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->select('id')
            ->from('for_update')
            ->forUpdate();

        self::assertEquals([1, 2], $qb1->fetchFirstColumn());
    }

    public function testForUpdateSkipLockedWhenSupported(): void
    {
        if (! $this->platformSupportsSkipLocked()) {
            self::markTestSkipped('The database platform does not support SKIP LOCKED.');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->select('id')
            ->from('for_update')
            ->where('id = 1')
            ->forUpdate();

        $this->connection->beginTransaction();

        self::assertEquals([1], $qb1->fetchFirstColumn());

        $params = TestUtil::getConnectionParams();

        if (TestUtil::isDriverOneOf('oci8')) {
            $params['driverOptions']['exclusive'] = true;
        }

        $connection2 = DriverManager::getConnection($params);

        $qb2 = $connection2->createQueryBuilder();
        $qb2->select('id')
            ->from('for_update')
            ->orderBy('id')
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED);

        self::assertEquals([2], $qb2->fetchFirstColumn());
    }

    public function testTypeCanBeAccessedFromDecoratingQueryBuilderInstance(): void
    {
        $qb1 = new class ($this->connection) extends QueryBuilder {
            public function __construct(protected QueryBuilder $concreteQueryBuilder, protected Connection $connection)
            {
                parent::__construct($connection);
            }

            /**
            Replacement for removed `getQueryPart('select')`& `getQueryParts()['select]`.
             *
             * @return string[]
             */
            public function getSelect(): array
            {
                return $this->concreteQueryBuilder->select;
            }

            /**
             * Replacement for removed `getQueryPart('from')`& `getQueryParts()['from']`.
             *
             * @return From[]
             */
            public function getFrom(): array
            {
                return $this->concreteQueryBuilder->from;
            }

            /**
             * Replacement for removed `getQueryPart('where')`& `getQueryParts()['where']`.
             *
             * @return CompositeExpression|string|null
             */
            public function getWhere(): CompositeExpression|string|null
            {
                return $this->concreteQueryBuilder->where;
            }

            /**
             * Replacement for removed `getQueryPart('having')` & `getQueryParts()['having']`.
             *
             * @return CompositeExpression|string|null
             */
            public function getHaving(): CompositeExpression|string|null
            {
                return $this->concreteQueryBuilder->having;
            }

            /**
             * Replacement for removed `getQueryPart('orderBy')` & `getQueryParts()['orderBy']`.
             *
             * @return string[]
             */
            public function getOrderBy(): array
            {
                return $this->concreteQueryBuilder->orderBy;
            }

            /**
             * Replacement for removed `getQueryPart('groupBy')` & `getQueryParts()['groupBy']`.
             *
             * @return string[]
             */
            public function getGroupBy(): array
            {
               return $this->concreteQueryBuilder->groupBy;
            }

            /**
             * @return array<string, Join[]>
             */
            public function getJoin(): array
            {
                return $this->concreteQueryBuilder->join;
            }

            public function select(string ...$expressions): QueryBuilder
            {
                $expressions = $this->quoteIdentifiersForSelect($expressions);
                $this->concreteQueryBuilder->select(...$expressions);
                return $this;
            }

            public function from(string $table, ?string $alias = null): QueryBuilder
            {
                $this->concreteQueryBuilder->from(
                    $this->connection->quoteIdentifier($table), $alias
                );

                return $this;
            }

            // ... for where(), andWhere(), orWhere(), having(), andHaving(), orHaving(), orderBy(), addOrderBy(),
            //     groupBy(), addGroupBy(), join(), innerJoin(), leftJoin(), rightJoin(), outerJoin(), ...
            // same wrapping with automatic quoting of identieres, and partly also values before adding it to the
            // internal `concreateQueryBuilder` instance.

            public function getType(): QueryType
            {
                return $this->concreteQueryBuilder->type;
            }

            /**
             * This access to the internal QueryBuilder is something our application provided since years. Due our own
             * policies we cannot remove this directly with the next version, the the old version is already LTS, which
             * means to late to deprecate.
             *
             * @return QueryBuilder
             */
            public function getConcreteQueryBuilder(): QueryBuilder
            {
                return $this->concreteQueryBuilder;
            }

            /**
             * Quotes an array of column names so it can be safely used, even if the name is a reserved name.
             * Takes into account the special case of the * placeholder that can only be used in SELECT type
             * statements.
             *
             * Delimiting style depends on the underlying database platform that is being used.
             *
             * @param array $input
             *
             * @throws \InvalidArgumentException
             */
            public function quoteIdentifiersForSelect(array $input): array
            {
                foreach ($input as &$select) {
                    [$fieldName, $alias, $suffix] = array_pad(
                        explode(
                            ' AS ',
                            str_ireplace(' as ', ' AS ', $select),
                            3
                        ),
                        3,
                        null
                    );
                    if (!empty($suffix)) {
                        throw new \InvalidArgumentException(
                            'QueryBuilder::quoteIdentifiersForSelect() could not parse the select ' . $select . '.',
                            1461170686
                        );
                    }

                    // The SQL * operator must not be quoted. As it can only occur either by itself
                    // or preceded by a tablename (tablename.*) check if the last character of a select
                    // expression is the * and quote only prepended table name. In all other cases the
                    // full expression is being quoted.
                    if (substr($fieldName, -2) === '.*') {
                        $select = $this->connection->quoteIdentifier(substr($fieldName, 0, -2)) . '.*';
                    } elseif ($fieldName !== '*') {
                        $select = $this->connection->quoteIdentifier($fieldName);
                    }

                    // Quote the alias for the current fieldName, if given
                    if (!empty($alias)) {
                        $select .= ' AS ' . $this->connection->quoteIdentifier($alias);
                    }
                }
                return $input;
            }

            /**
             * @return Result
             */
            public function executeQuery(): Result
            {
                $originalQueryBuilder = clone $this->concreteQueryBuilder;

                try {
                    $this->applyAutomaticQueryRestrictions();
                    $result = $this->concreteQueryBuilder->executeQuery();
                } finally {
                    $this->concreteQueryBuilder = $originalQueryBuilder;
                }

                return $result;
            }

            /**
             * Demonstrates some automatic restrictions applies which the system sets on execution, and the user of
             * the QueryBuilder does not need to take action on.
             *
             * @return void
             */
            private function applyAutomaticQueryRestrictions(): void
            {
                // Automaticly apply some application constraints and restriction based on multi-language context,
                // signed-in user context and other stuff as a way to keep it away from application extension authors.
                if ($this->concreteQueryBuilder->from[0] instanceof From
                    && $this->concreteQueryBuilder->from[0]->table === 'some_table'
                ) {
                    $contestBackendLanguageId = 123;
                    $this->andWhere(
                        $this->expr()->eq('sys_language_id', $this->createNamedParameter($contestBackendLanguageId))
                    );
                }
            }
        };
        $qb1->select('id')->from('for_update');
        $qb1->getConcreteQueryBuilder()->where(
            $qb1->expr()->eq($this->connection->quoteIdentifier('id', $qb1->createNamedParameter(123))),
        );

        self::assertSame($qb1->getType() === QueryType::SELECT);
        self::assertCount(1, $qb1->getFrom());
        self::assertInstanceOf(From::class, $qb1->getFrom[0]);

        $qb1->getWhere();
        $qb1->getFrom();
        $qb1->getSelect();
    }



    public function testForUpdateSkipLockedWhenNotSupported(): void
    {
        if ($this->platformSupportsSkipLocked()) {
            self::markTestSkipped('The database platform supports SKIP LOCKED.');
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('for_update')
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED);

        self::expectException(Exception::class);
        $qb->executeQuery();
    }

    private function platformSupportsSkipLocked(): bool
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof DB2Platform) {
            return false;
        }

        if ($platform instanceof MySQLPlatform) {
            return $platform instanceof MySQL80Platform;
        }

        if ($platform instanceof MariaDBPlatform) {
            return $platform instanceof MariaDB1060Platform;
        }

        return ! $platform instanceof SQLitePlatform;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\NotImplemented;
use Doctrine\DBAL\Schema\Name\GenericName;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function array_map;
use function assert;
use function count;
use function implode;
use function str_replace;

/**
 * The abstract asset allows to reset the name of all assets without publishing this to the public userland.
 *
 * This encapsulation hack is necessary to keep a consistent state of the database schema. Say we have a list of tables
 * array($tableName => Table($tableName)); if you want to rename the table, you have to make sure this does not get
 * recreated during schema migration.
 *
 * @internal This class should be extended only by DBAL itself.
 *
 * @template N of Name
 */
abstract class AbstractAsset
{
    protected string $_name = '';

    /** @deprecated */
    protected bool $_quoted = false;

    /** @var list<Identifier> */
    private array $identifiers = [];

    public function __construct(string $name)
    {
        if ($name !== '') {
            try {
                $parsedName = $this->getNameParser()->parse($name);
            } catch (Parser\Exception $e) {
                throw InvalidName::fromParserException($name, $e);
            }
        } else {
            $parsedName = null;
        }

        $this->setName($parsedName);

        if ($parsedName === null) {
            return;
        }

        if ($parsedName instanceof UnqualifiedName) {
            $identifiers = [$parsedName->getIdentifier()];
        } elseif ($parsedName instanceof OptionallyQualifiedName) {
            $unqualifiedName = $parsedName->getUnqualifiedName();
            $qualifier       = $parsedName->getQualifier();

            $identifiers = $qualifier !== null
                ? [$qualifier, $unqualifiedName]
                : [$unqualifiedName];
        } elseif ($parsedName instanceof GenericName) {
            $identifiers = $parsedName->getIdentifiers();
        } else {
            return;
        }

        $count = count($identifiers);
        assert($count > 0);

        $name = $identifiers[$count - 1];

        $this->_name       = $name->getValue();
        $this->_quoted     = $name->isQuoted();
        $this->identifiers = $identifiers;
    }

    /**
     * Returns a parser for parsing the object name.
     *
     * @deprecated Parse the name in the constructor instead.
     *
     * @return Parser<N>
     */
    protected function getNameParser(): Parser
    {
        throw NotImplemented::fromMethod(static::class, __FUNCTION__);
    }

    /**
     * Sets the object name.
     *
     * @deprecated Set the name in the constructor instead.
     *
     * @param ?N $name
     */
    protected function setName(?Name $name): void
    {
        throw NotImplemented::fromMethod(static::class, __FUNCTION__);
    }

    /**
     * Trim quotes from the identifier.
     */
    protected function trimQuotes(string $identifier): string
    {
        return str_replace(['`', '"', '[', ']'], '', $identifier);
    }

    /**
     * Returns the name of this schema asset.
     *
     * @deprecated Use {@see NamedObject::getObjectName()} or {@see OptionallyQualifiedName::getObjectName()} instead.
     *             In SQL context, convert the resulting {@see Name} to SQL using {@see Name::toSQL()}. In other
     *             contexts, convert the resulting name to string using {@see Name::toString()}.
     */
    public function getName(): string
    {
        return implode('.', array_map(
            static fn (Identifier $identifier): string => $identifier->getValue(),
            $this->identifiers,
        ));
    }
}

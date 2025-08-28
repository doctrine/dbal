<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;

/**
 * Representation of a Database View.
 *
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
final class View extends AbstractNamedObject
{
    /** @internal Use {@link View::editor()} to instantiate an editor and {@link ViewEditor::create()} to create a view. */
    public function __construct(string $name, private readonly string $sql)
    {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            $parsedName = $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }

        parent::__construct($parsedName);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Instantiates a new view editor.
     */
    public static function editor(): ViewEditor
    {
        return new ViewEditor();
    }

    /**
     * Instantiates a new view editor and initializes it with the view's properties.
     */
    public function edit(): ViewEditor
    {
        return self::editor()
            ->setName($this->getObjectName())
            ->setSQL($this->sql);
    }
}

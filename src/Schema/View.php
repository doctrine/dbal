<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;

/**
 * Representation of a Database View.
 *
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
final class View extends AbstractNamedObject
{
    /** @internal Use {@link View::editor()} to instantiate an editor and {@link ViewEditor::create()} to create a view. */
    public function __construct(OptionallyQualifiedName $name, private readonly string $sql)
    {
        parent::__construct($name);
    }

    public function getSQL(): string
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

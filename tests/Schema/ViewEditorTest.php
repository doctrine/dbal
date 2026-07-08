<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidViewDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\View;
use PHPUnit\Framework\TestCase;

class ViewEditorTest extends TestCase
{
    public function testSetUnquotedName(): void
    {
        $view = View::editor()
            ->setUnquotedName('active_users')
            ->setSQL('SELECT 1')
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::unquoted('active_users'),
            $view->getObjectName(),
        );
    }

    public function testSetQuotedName(): void
    {
        $view = View::editor()
            ->setQuotedName('active_users')
            ->setSQL('SELECT 1')
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::quoted('active_users'),
            $view->getObjectName(),
        );
    }

    public function testNameNotSet(): void
    {
        $editor = View::editor()
            ->setSQL('SELECT 1');

        $this->expectException(InvalidViewDefinition::class);

        $editor->create();
    }

    public function testSqlNotSet(): void
    {
        $editor = View::editor()
            ->setUnquotedName('active_users');

        $this->expectException(InvalidViewDefinition::class);

        $editor->create();
    }

    public function testEdit(): void
    {
        $view = View::editor()
            ->setUnquotedName('active_users')
            ->setSQL('SELECT 1')
            ->create();

        self::assertEquals($view, $view->edit()->create());
    }
}

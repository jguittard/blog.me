<?php

declare(strict_types=1);

namespace AdminTest\Form;

use Admin\Form\CategoryForm;
use Admin\Form\TagForm;
use PHPUnit\Framework\TestCase;

final class CategoryFormTest extends TestCase
{
    public function testCategoryAcceptsNameAndOptionalDescription(): void
    {
        $form = new CategoryForm();
        $form->setData(['name' => 'Meteorology', 'description' => '']);

        self::assertTrue($form->isValid());
        /** @var array<string, mixed> $data */
        $data = $form->getData();
        self::assertNull($data['description']);
    }

    public function testCategoryRejectsEmptyName(): void
    {
        $form = new CategoryForm();
        $form->setData(['name' => '']);

        self::assertFalse($form->isValid());
        self::assertArrayHasKey('name', $form->getMessages());
    }

    public function testTagRejectsEmptyName(): void
    {
        $form = new TagForm();
        $form->setData(['name' => '']);

        self::assertFalse($form->isValid());
        self::assertArrayHasKey('name', $form->getMessages());
    }

    public function testTagAcceptsName(): void
    {
        $form = new TagForm();
        $form->setData(['name' => 'cessna 172']);

        self::assertTrue($form->isValid());
        /** @var array<string, mixed> $data */
        $data = $form->getData();
        self::assertSame('cessna 172', $data['name']);
    }
}

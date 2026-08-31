<?php

declare(strict_types=1);

namespace AdminTest\Form;

use Admin\Form\PostForm;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function str_repeat;

final class PostFormTest extends TestCase
{
    /** @return array<string, string> */
    private function validData(): array
    {
        return [
            'title'       => 'A Valid Title',
            'body'        => 'Some body content.',
            'excerpt'     => '',
            'categoryId'  => '',
            'status'      => 'published',
            'publishedAt' => '',
            'tags'        => 'one, two',
            'imageUrl'    => '',
            'imageAlt'    => '',
        ];
    }

    public function testAcceptsAValidRow(): void
    {
        $form = new PostForm(['cat-1' => 'Nav']);
        $form->setData($this->validData());

        self::assertTrue($form->isValid(), (string) json_encode($form->getMessages()));
    }

    public function testRejectsEmptyTitleAndBody(): void
    {
        $form = new PostForm();
        $form->setData(['title' => '', 'body' => '', 'status' => 'draft']);

        self::assertFalse($form->isValid());
        self::assertArrayHasKey('title', $form->getMessages());
        self::assertArrayHasKey('body', $form->getMessages());
    }

    public function testRejectsUnknownStatus(): void
    {
        $form = new PostForm();
        $form->setData(['title' => 'x', 'body' => 'y', 'status' => 'bogus']);

        self::assertFalse($form->isValid());
        self::assertArrayHasKey('status', $form->getMessages());
    }

    public function testRejectsNonUrlImageAndUnknownCategory(): void
    {
        $form = new PostForm(['cat-1' => 'Nav']);
        $form->setData([
            'title'      => 'x',
            'body'       => 'y',
            'status'     => 'draft',
            'imageUrl'   => 'not a url',
            'categoryId' => 'cat-999',
        ] + $this->validData());

        self::assertFalse($form->isValid());
        self::assertArrayHasKey('imageUrl', $form->getMessages());
        self::assertArrayHasKey('categoryId', $form->getMessages());
    }

    public function testRejectsOverLongExcerptAndBadPublishDate(): void
    {
        $form = new PostForm();
        $form->setData([
            'title'       => 'x',
            'body'        => 'y',
            'status'      => 'draft',
            'excerpt'     => str_repeat('a', 501),
            'publishedAt' => '2026/01/01 10:00',
        ] + $this->validData());

        self::assertFalse($form->isValid());
        self::assertArrayHasKey('excerpt', $form->getMessages());
        self::assertArrayHasKey('publishedAt', $form->getMessages());
    }

    public function testFiltersTrimAndStripTagsFromTitle(): void
    {
        $form = new PostForm();
        $form->setData(['title' => '  <b>Hi</b>  ', 'body' => 'ok', 'status' => 'draft'] + $this->validData());

        self::assertTrue($form->isValid());
        /** @var array<string, mixed> $data */
        $data = $form->getData();
        self::assertSame('Hi', $data['title']);
    }
}

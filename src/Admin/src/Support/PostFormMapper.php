<?php

declare(strict_types=1);

namespace Admin\Support;

use Admin\Form\PostForm;
use App\Domain\Entity\Post;
use App\Domain\Value\PostStatus;
use DateTimeImmutable;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function implode;
use function is_string;
use function trim;

/**
 * Translates the filtered {@see PostForm} array to / from the immutable
 * {@see Post} entity, keeping the domain mutators as the single source of truth.
 */
final class PostFormMapper
{
    /** @param array<string, mixed> $data */
    public function create(array $data, string $authorId): Post
    {
        $post = Post::draft(
            authorId: $authorId,
            title: $this->str($data['title'] ?? ''),
            body: $this->str($data['body'] ?? ''),
            categoryId: $this->nullable($data['categoryId'] ?? null),
            excerpt: $this->nullable($data['excerpt'] ?? null),
        );

        return $this->applyImageAndStatus($post, $data);
    }

    /** @param array<string, mixed> $data */
    public function apply(Post $post, array $data): Post
    {
        $title = $this->str($data['title'] ?? '');
        if ($title !== $post->title) {
            $post = $post->rename($title);
        }

        $post = $post
            ->editBody($this->str($data['body'] ?? ''), $this->nullable($data['excerpt'] ?? null))
            ->reclassify($this->nullable($data['categoryId'] ?? null));

        return $this->applyImageAndStatus($post, $data);
    }

    /**
     * Form-ready array for populating the edit form.
     *
     * @param  list<string> $tagNames
     * @return array<string, string>
     */
    public function toArray(Post $post, array $tagNames): array
    {
        return [
            'title'       => $post->title,
            'body'        => $post->body,
            'excerpt'     => $post->excerpt ?? '',
            'categoryId'  => $post->categoryId ?? '',
            'status'      => $post->status->value,
            'publishedAt' => $post->publishedAt?->format(PostForm::PUBLISHED_AT_FORMAT) ?? '',
            'imageUrl'    => $post->imageUrl ?? '',
            'imageAlt'    => $post->imageAlt ?? '',
            'tags'        => implode(', ', $tagNames),
        ];
    }

    /** @return list<string> */
    public function splitTags(mixed $raw): array
    {
        $names = array_map('trim', explode(',', is_string($raw) ? $raw : ''));

        return array_values(array_filter($names, static fn (string $n): bool => $n !== ''));
    }

    /** @param array<string, mixed> $data */
    private function applyImageAndStatus(Post $post, array $data): Post
    {
        $post = $post->withImage(
            $this->nullable($data['imageUrl'] ?? null),
            $this->nullable($data['imageAlt'] ?? null),
        );

        $at = $this->nullable($data['publishedAt'] ?? null);

        return match (PostStatus::from($this->str($data['status'] ?? 'draft'))) {
            PostStatus::Published => $post->publish($this->publishInstant($post, $at)),
            PostStatus::Draft     => $post->unpublish(),
            PostStatus::Archived  => $post->archive(),
        };
    }

    /** Keep an already-set publish date when the form leaves it blank. */
    private function publishInstant(Post $post, ?string $at): ?DateTimeImmutable
    {
        if ($at !== null) {
            $parsed = DateTimeImmutable::createFromFormat('!' . PostForm::PUBLISHED_AT_FORMAT, $at);

            return $parsed === false ? null : $parsed;
        }

        return $post->publishedAt;
    }

    private function str(mixed $v): string
    {
        return is_string($v) ? trim($v) : '';
    }

    private function nullable(mixed $v): ?string
    {
        $v = $this->str($v);

        return $v === '' ? null : $v;
    }
}

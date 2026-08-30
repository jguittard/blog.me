<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use Admin\Form\PostForm;
use Admin\Support\PostWriter;
use App\Domain\Repository\PostRepositoryInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PostEditHandler extends AbstractAdminHandler
{
    public function __construct(
        TemplateRendererInterface $template,
        UrlHelper $url,
        CurrentUserProvider $currentUser,
        private readonly PostRepositoryInterface $posts,
        private readonly PostForm $form,
        private readonly PostWriter $writer,
    ) {
        parent::__construct($template, $url, $currentUser);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $post = $this->posts->find((string) $request->getAttribute('id', ''));
        if ($post === null) {
            return $this->notFound();
        }

        if ($request->getMethod() === 'POST') {
            $this->form->setData((array) $request->getParsedBody());

            if ($this->form->isValid()) {
                /** @var array<string, mixed> $data */
                $data = $this->form->getData();
                $this->writer->update($post, $data);

                return $this->redirectToRoute('admin.posts.index');
            }
        } else {
            $this->form->setData($this->writer->formData($post));
        }

        return $this->view('admin::posts/form', [
            'form'    => $this->form,
            'heading' => 'Edit post',
            'action'  => $this->url->generate('admin.posts.edit', ['id' => $post->id]),
        ]);
    }
}

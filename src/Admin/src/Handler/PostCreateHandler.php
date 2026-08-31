<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use Admin\Form\PostForm;
use Admin\Support\PostWriter;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PostCreateHandler extends AbstractAdminHandler
{
    public function __construct(
        TemplateRendererInterface $template,
        UrlHelper $url,
        CurrentUserProvider $currentUser,
        private readonly PostForm $form,
        private readonly PostWriter $writer,
    ) {
        parent::__construct($template, $url, $currentUser);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $this->form->setData((array) $request->getParsedBody());

            if ($this->form->isValid()) {
                /** @var array<string, mixed> $data */
                $data = $this->form->getData();
                $this->writer->create($data, $this->currentUser->get()->id);

                return $this->redirectToRoute('admin.posts.index');
            }
        }

        return $this->view('admin::posts/form', [
            'form'    => $this->form,
            'heading' => 'New post',
            'action'  => $this->url->generate('admin.posts.create'),
        ]);
    }
}

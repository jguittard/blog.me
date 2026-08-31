<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use Admin\Form\TagForm;
use App\Domain\Repository\TagRepositoryInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TagEditHandler extends AbstractAdminHandler
{
    public function __construct(
        TemplateRendererInterface $template,
        UrlHelper $url,
        CurrentUserProvider $currentUser,
        private readonly TagRepositoryInterface $tags,
        private readonly TagForm $form,
    ) {
        parent::__construct($template, $url, $currentUser);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tag = $this->tags->find((string) $request->getAttribute('id', ''));
        if ($tag === null) {
            return $this->notFound();
        }

        if ($request->getMethod() === 'POST') {
            $this->form->setData((array) $request->getParsedBody());

            if ($this->form->isValid()) {
                /** @var array{name: string} $data */
                $data = $this->form->getData();
                $this->tags->save($tag->rename($data['name']));

                return $this->redirectToRoute('admin.tags.index');
            }
        } else {
            $this->form->setData(['name' => $tag->name]);
        }

        return $this->view('admin::tags/form', [
            'form'    => $this->form,
            'heading' => 'Edit tag',
            'action'  => $this->url->generate('admin.tags.edit', ['id' => $tag->id]),
        ]);
    }
}

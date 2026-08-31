<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use Admin\Form\TagForm;
use App\Domain\Entity\Tag;
use App\Domain\Repository\TagRepositoryInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TagCreateHandler extends AbstractAdminHandler
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
        if ($request->getMethod() === 'POST') {
            $this->form->setData((array) $request->getParsedBody());

            if ($this->form->isValid()) {
                /** @var array{name: string} $data */
                $data = $this->form->getData();
                $this->tags->save(Tag::named($data['name']));

                return $this->redirectToRoute('admin.tags.index');
            }
        }

        return $this->view('admin::tags/form', [
            'form'    => $this->form,
            'heading' => 'New tag',
            'action'  => $this->url->generate('admin.tags.create'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Admin\Handler;

use Admin\CurrentUser\CurrentUserProvider;
use Admin\Form\CategoryForm;
use App\Domain\Entity\Category;
use App\Domain\Repository\CategoryRepositoryInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

final class CategoryCreateHandler extends AbstractAdminHandler
{
    public function __construct(
        TemplateRendererInterface $template,
        UrlHelper $url,
        CurrentUserProvider $currentUser,
        private readonly CategoryRepositoryInterface $categories,
        private readonly CategoryForm $form,
    ) {
        parent::__construct($template, $url, $currentUser);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $this->form->setData((array) $request->getParsedBody());

            if ($this->form->isValid()) {
                /** @var array{name: string, description?: string|null} $data */
                $data = $this->form->getData();

                $description = $data['description'] ?? null;
                $description = is_string($description) ? $description : null;

                $this->categories->save(Category::create($data['name'], $description));

                return $this->redirectToRoute('admin.categories.index');
            }
        }

        return $this->view('admin::categories/form', [
            'form'    => $this->form,
            'heading' => 'New category',
            'action'  => $this->url->generate('admin.categories.create'),
        ]);
    }
}

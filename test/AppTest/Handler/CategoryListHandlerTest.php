<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Domain\Entity\Category;
use App\Domain\Repository\CategoryRepositoryInterface;
use App\Handler\CategoryListHandler;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class CategoryListHandlerTest extends TestCase
{
    public function testRendersAllCategories(): void
    {
        $categories = [Category::create('Navigation'), Category::create('Air Law')];

        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->expects($this->once())->method('all')->willReturn($categories);

        $template = $this->createMock(TemplateRendererInterface::class);
        $template->expects($this->once())
            ->method('render')
            ->with('app::category-list', ['categories' => $categories])
            ->willReturn('<html></html>');

        $response = (new CategoryListHandler($repo, $template))
            ->handle($this->createMock(ServerRequestInterface::class));

        self::assertSame(200, $response->getStatusCode());
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\ArticleService;
use App\Service\Viewer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The editor pages, shared by the create and the edit flows of an article.
 */
final class EditorController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ArticleService $articleService,
        private readonly CsrfTokenManagerInterface $csrfTokens,
        private readonly Viewer $viewer,
    ) {
    }

    #[Route('/editor', name: 'web_editor_create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('editor/edit.html.twig');
    }

    #[Route('/editor', name: 'web_article_create', methods: ['POST'])]
    public function store(Request $request): RedirectResponse
    {
        $this->validateCsrf($request, 'editor');

        $title = trim($request->request->getString('title'));
        $description = trim($request->request->getString('description'));
        $body = $request->request->getString('body');

        $errors = [];
        if ('' === $title) {
            $errors[] = 'title is required';
        }
        if ('' === $description) {
            $errors[] = 'description is required';
        }
        if ('' === trim($body)) {
            $errors[] = 'body is required';
        }

        if ([] !== $errors) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToRoute('web_editor_create');
        }

        $article = $this->articleService->create(
            $title,
            $description,
            $body,
            $this->tagList($request),
            $this->viewer->user(),
        );

        return $this->redirectToRoute('web_article_show', ['slug' => $article->getSlug()]);
    }

    #[Route('/editor/{slug}', name: 'web_editor_edit', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function edit(string $slug): Response
    {
        $article = $this->article($slug);

        $this->denyUnlessAuthor($article);

        $tags = [];
        foreach ($article->getTags() as $tag) {
            $tags[] = $tag->getName();
        }

        return $this->render('editor/edit.html.twig', [
            'article' => $article,
            'article_tags' => implode(', ', $tags),
        ]);
    }

    #[Route('/editor/{slug}', name: 'web_article_update', methods: ['POST'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function update(string $slug, Request $request): RedirectResponse
    {
        $this->validateCsrf($request, 'editor');

        $article = $this->article($slug);

        $this->denyUnlessAuthor($article);

        $title = $request->request->getString('title');
        $description = $request->request->getString('description');
        $body = $request->request->getString('body');

        if ('' === trim($title) || '' === trim($description) || '' === trim($body)) {
            $this->addFlash('error', 'Title, description and body are required.');

            return $this->redirectToRoute('web_editor_edit', ['slug' => $slug]);
        }

        $this->articleService->update(
            $article,
            $title,
            $description,
            $body,
            $this->tagList($request),
        );

        return $this->redirectToRoute('web_article_show', ['slug' => $article->getSlug()]);
    }

    /**
     * Deletes the article of the author.
     */
    #[Route('/article/{slug}/delete', name: 'web_article_delete', methods: ['POST'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function delete(string $slug, Request $request): RedirectResponse
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken('delete'.$slug, $request->request->getString('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $article = $this->article($slug);

        $this->denyUnlessAuthor($article);

        $this->articleService->delete($article);

        $this->addFlash('success', 'Your article has been deleted.');

        return $this->redirectToRoute('web_home');
    }

    /**
     * @return list<string>|null
     */
    private function tagList(Request $request): ?array
    {
        $raw = $request->request->getString('tagList');

        $names = [];
        foreach (explode(',', $raw) as $name) {
            $name = trim($name);
            if ('' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function denyUnlessAuthor(Article $article): void
    {
        if ($article->getAuthor()->getId() !== $this->viewer->user()->getId()) {
            $this->addFlash('error', 'Only the author can edit this article.');
            throw $this->createAccessDeniedException('Not the author of the article.');
        }
    }

    private function validateCsrf(Request $request, string $tokenId): void
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken($tokenId, $request->request->getString('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * Finds the article by slug, rendering web-friendly 404s.
     */
    private function article(string $slug): Article
    {
        $article = $this->articles->findOneBySlug($slug);

        if (null === $article) {
            throw new NotFoundHttpException('Article not found.');
        }

        return $article;
    }
}

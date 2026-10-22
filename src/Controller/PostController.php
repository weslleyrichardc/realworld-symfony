<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use App\Service\ArticleService;
use App\Service\Viewer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The web pages of the article resource: the article page with its comments
 * and the favorite toggle.
 */
final class PostController extends AbstractController
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly CommentRepository $comments,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokens,
        private readonly ArticleService $articleService,
        private readonly Viewer $viewer,
    ) {
    }

    #[Route('/article/{slug}', name: 'web_article_show', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function show(string $slug): Response
    {
        $article = $this->articles->findOneBySlugOrFail($slug);

        $favoritedIds = $this->viewer->favoritedIds([$article]);
        $viewer = $this->viewer->user();

        return $this->render('post/show.html.twig', [
            'article' => $article,
            'favorited' => isset($favoritedIds[$article->getId()->toRfc4122()]),
            'comments' => $this->comments->findByArticle($article),
            'is_author' => null !== $viewer && $viewer->getId() === $article->getAuthor()->getId(),
        ]);
    }

    /**
     * Adds the article to, or removes it from, the favorites of the current
     * user. Slow websites may only support `POST`, hence the `op` switch.
     */
    #[Route('/article/{slug}/favorite', name: 'web_article_favorite', methods: ['POST'])]
    public function favorite(string $slug, Request $request): RedirectResponse
    {
        $this->validateCsrf($request, 'favorite.'.$slug);
        $this->denyUnlessAuthenticated();

        $article = $this->articles->findOneBySlugOrFail($slug);

        if ('unfavorite' === $request->request->getString('op')) {
            $this->articleService->unfavorite($article, $this->viewer->user());
        } else {
            $this->articleService->favorite($article, $this->viewer->user());
        }

        return $this->redirectToRoute('web_article_show', ['slug' => $article->getSlug()]);
    }

    /**
     * Adds a comment to the article.
     */
    #[Route('/article/{slug}/comments', name: 'web_comment_create', methods: ['POST'])]
    public function comment(string $slug, Request $request): RedirectResponse
    {
        $this->validateCsrf($request, 'comments.'.$slug);
        $this->denyUnlessAuthenticated();

        $article = $this->articles->findOneBySlugOrFail($slug);

        $body = trim($request->request->getString('body'));

        if ('' === $body) {
            $this->addFlash('error', 'A comment cannot be empty.');

            return $this->redirectToRoute('web_article_show', ['slug' => $slug]);
        }

        $comment = new Comment($body, $article, $this->viewer->user());

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $this->redirectToRoute('web_article_show', ['slug' => $article->getSlug(), '_fragment' => 'comments']);
    }

    /**
     * Deletes a comment of the article, only when the current user wrote it.
     */
    #[Route('/article/{slug}/comments/{id<\d+>}/delete', name: 'web_comment_delete', methods: ['POST'])]
    public function deleteComment(string $slug, int $id, Request $request): RedirectResponse
    {
        $this->validateCsrf($request, 'comment.'.$id);
        $this->denyUnlessAuthenticated();

        $article = $this->articles->findOneBySlugOrFail($slug);
        $comment = $this->comments->find($id);

        if (null === $comment || $comment->getArticle() !== $article) {
            throw ApiException::notFound('comment');
        }

        if ($comment->getAuthor()->getId() !== $this->viewer->user()->getId()) {
            throw ApiException::forbidden('comment');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        return $this->redirectToRoute('web_article_show', ['slug' => $article->getSlug(), '_fragment' => 'comments']);
    }

    /**
     * @throws ApiException when the visitor carries no valid session
     */
    private function denyUnlessAuthenticated(): void
    {
        if (!$this->viewer->isAuthenticated()) {
            throw ApiException::unauthorized('is required');
        }
    }

    private function validateCsrf(Request $request, string $tokenId): void
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken($tokenId, $request->request->getString('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}

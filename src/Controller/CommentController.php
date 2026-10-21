<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\User;
use App\Exception\ApiException;
use App\Http\Payload;
use App\Presenter\CommentPresenter;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use App\Service\Viewer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoints of the comment resource.
 */
final class CommentController
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly CommentRepository $comments,
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentPresenter $presenter,
        private readonly Viewer $viewer,
    ) {
    }

    /**
     * Lists the comments of an article.
     */
    #[Route('/api/articles/{slug}/comments', name: 'api_comment_list', methods: ['GET'])]
    public function list(string $slug): JsonResponse
    {
        $article = $this->articles->findOneBySlugOrFail($slug);
        $followedUsernames = $this->viewer->followedUsernames();

        $data = [];
        foreach ($this->comments->findByArticle($article) as $comment) {
            $data[] = $this->presenter->present($comment, $followedUsernames);
        }

        return new JsonResponse(['comments' => $data]);
    }

    /**
     * Adds a comment to an article.
     */
    #[Route('/api/articles/{slug}/comments', name: 'api_comment_create', methods: ['POST'])]
    public function create(string $slug, Request $request): JsonResponse
    {
        $viewer = $this->requireUser();
        $article = $this->articles->findOneBySlugOrFail($slug);

        $payload = Payload::fromRequest($request, 'comment');

        $body = $payload->requiredText('body');

        if ($payload->hasErrors()) {
            throw ApiException::unprocessable($payload->errors());
        }

        $comment = new Comment($body, $article, $viewer);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return new JsonResponse(
            ['comment' => $this->presenter->present($comment, $this->viewer->followedUsernames())],
            Response::HTTP_CREATED,
        );
    }

    /**
     * Deletes a comment of an article.
     */
    #[Route('/api/articles/{slug}/comments/{id<\d+>}', name: 'api_comment_delete', methods: ['DELETE'])]
    public function delete(string $slug, int $id): Response
    {
        $viewer = $this->requireUser();
        $article = $this->articles->findOneBySlugOrFail($slug);

        $comment = $this->comments->find($id);

        if (null === $comment || $comment->getArticle() !== $article) {
            throw ApiException::notFound('comment');
        }

        if ($comment->getAuthor()->getId() !== $viewer->getId()) {
            throw ApiException::forbidden('comment');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws ApiException when the request carries no valid token
     */
    private function requireUser(): User
    {
        $user = $this->viewer->user();

        if (null === $user) {
            throw ApiException::unauthorized();
        }

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Exception\ApiException;
use App\Http\Payload;
use App\Presenter\ArticlePresenter;
use App\Repository\ArticleRepository;
use App\Service\ArticleService;
use App\Service\Viewer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoints of the article resource.
 */
final class ArticleController
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ArticleService $articleService,
        private readonly ArticlePresenter $presenter,
        private readonly Viewer $viewer,
    ) {
    }

    /**
     * Lists the articles matching the given filters.
     */
    #[Route('/api/articles', name: 'api_article_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        [$limit, $offset] = $this->pagination($request);

        $tag = $this->filter($request, 'tag');
        $author = $this->filter($request, 'author');
        $favorited = $this->filter($request, 'favorited');

        $articles = $this->articles->findFiltered($tag, $author, $favorited, $limit, $offset);
        $count = $this->articles->countFiltered($tag, $author, $favorited);

        return new JsonResponse($this->presentMany($articles, $count));
    }

    /**
     * Lists the articles written by the users the authenticated one follows.
     */
    #[Route('/api/articles/feed', name: 'api_article_feed', methods: ['GET'])]
    public function feed(Request $request): JsonResponse
    {
        $viewer = $this->requireUser();
        [$limit, $offset] = $this->pagination($request);

        $articles = $this->articles->findFeed($viewer, $limit, $offset);
        $count = $this->articles->countFeed($viewer);

        return new JsonResponse($this->presentMany($articles, $count));
    }

    /**
     * Creates an article.
     */
    #[Route('/api/articles', name: 'api_article_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $viewer = $this->requireUser();

        $payload = Payload::fromRequest($request, 'article');

        $title = $payload->requiredString('title');
        $description = $payload->requiredString('description');
        $body = $payload->requiredText('body');
        $tagList = $payload->optionalStringList('tagList');

        if ($payload->hasErrors()) {
            throw ApiException::unprocessable($payload->errors());
        }

        $article = $this->articleService->create($title, $description, $body, $tagList, $viewer);

        return new JsonResponse(['article' => $this->presentOne($article)], Response::HTTP_CREATED);
    }

    /**
     * Returns a single article.
     */
    #[Route('/api/articles/{slug}', name: 'api_article_show', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        $article = $this->articles->findOneBySlugOrFail($slug);

        return new JsonResponse(['article' => $this->presentOne($article)]);
    }

    /**
     * Updates an article.
     */
    #[Route('/api/articles/{slug}', name: 'api_article_update', methods: ['PUT'])]
    public function update(string $slug, Request $request): JsonResponse
    {
        $viewer = $this->requireUser();
        $article = $this->articles->findOneBySlugOrFail($slug);

        if ($article->getAuthor()->getId() !== $viewer->getId()) {
            throw ApiException::forbidden('article');
        }

        $payload = Payload::fromRequest($request, 'article');

        $title = $payload->optionalString('title');
        $description = $payload->optionalString('description');
        $body = $payload->optionalText('body');
        $tagList = $payload->optionalStringList('tagList');

        if ($payload->hasErrors()) {
            throw ApiException::unprocessable($payload->errors());
        }

        $this->articleService->update($article, $title, $description, $body, $tagList);

        return new JsonResponse(['article' => $this->presentOne($article)]);
    }

    /**
     * Deletes an article.
     */
    #[Route('/api/articles/{slug}', name: 'api_article_delete', methods: ['DELETE'])]
    public function delete(string $slug): Response
    {
        $viewer = $this->requireUser();
        $article = $this->articles->findOneBySlugOrFail($slug);

        if ($article->getAuthor()->getId() !== $viewer->getId()) {
            throw ApiException::forbidden('article');
        }

        $this->articleService->delete($article);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Adds the article to the favorites of the authenticated user.
     */
    #[Route('/api/articles/{slug}/favorite', name: 'api_article_favorite', methods: ['POST'])]
    public function favorite(string $slug): JsonResponse
    {
        $viewer = $this->requireUser();
        $article = $this->articles->findOneBySlugOrFail($slug);

        $this->articleService->favorite($article, $viewer);

        return new JsonResponse(['article' => $this->presentOne($article)]);
    }

    /**
     * Removes the article from the favorites of the authenticated user.
     */
    #[Route('/api/articles/{slug}/favorite', name: 'api_article_unfavorite', methods: ['DELETE'])]
    public function unfavorite(string $slug): JsonResponse
    {
        $viewer = $this->requireUser();
        $article = $this->articles->findOneBySlugOrFail($slug);

        $this->articleService->unfavorite($article, $viewer);

        return new JsonResponse(['article' => $this->presentOne($article)]);
    }

    /**
     * Renders a list of articles. The body is left out, as required by the
     * specification.
     *
     * @param list<Article> $articles
     *
     * @return array{articles: list<array<string, mixed>>, articlesCount: int}
     */
    private function presentMany(array $articles, int $count): array
    {
        $favoritedIds = $this->viewer->favoritedIds($articles);
        $followedUsernames = $this->viewer->followedUsernames();
        $tagNames = $this->articles->findTagNamesByArticles($articles);

        $data = [];
        foreach ($articles as $article) {
            $data[] = $this->presenter->present(
                $article,
                $favoritedIds,
                $followedUsernames,
                $tagNames[$article->getId()->toRfc4122()] ?? [],
                false,
            );
        }

        return ['articles' => $data, 'articlesCount' => $count];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOne(Article $article): array
    {
        return $this->presenter->present(
            $article,
            $this->viewer->favoritedIds([$article]),
            $this->viewer->followedUsernames(),
        );
    }

    /**
     * Reads the `limit` and `offset` query parameters.
     *
     * @return array{int, int}
     */
    private function pagination(Request $request): array
    {
        $limit = $this->integer($request, 'limit', self::DEFAULT_LIMIT);
        $offset = $this->integer($request, 'offset', 0);

        $limit = max(1, min($limit, self::MAX_LIMIT));

        return [$limit, max(0, $offset)];
    }

    private function integer(Request $request, string $key, int $default): int
    {
        $value = $request->query->get($key);

        if (!is_string($value) || '' === trim($value) || !is_numeric(trim($value))) {
            return $default;
        }

        return (int) trim($value);
    }

    private function filter(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
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

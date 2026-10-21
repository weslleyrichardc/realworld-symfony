<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ArticleApiTest extends ApiTestCase
{
    public function testItCreatesAnArticle(): void
    {
        $token = $this->register('jake');

        $response = $this->json('POST', '/api/articles', [
            'article' => [
                'title' => 'How to train your dragon',
                'description' => 'Ever wonder how?',
                'body' => 'You have to believe',
                'tagList' => ['dragons', 'training'],
            ],
        ], $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('How to train your dragon', $response['article']['title']);
        self::assertSame('how-to-train-your-dragon', $response['article']['slug']);
        self::assertSame('Ever wonder how?', $response['article']['description']);
        self::assertSame('You have to believe', $response['article']['body']);
        self::assertSame(['dragons', 'training'], $response['article']['tagList']);
        self::assertFalse($response['article']['favorited']);
        self::assertSame(0, $response['article']['favoritesCount']);
        self::assertSame('jake', $response['article']['author']['username']);
    }

    public function testItRefusesToCreateAnArticleWithoutAToken(): void
    {
        $this->json('POST', '/api/articles', [
            'article' => ['title' => 'Title', 'description' => 'Description', 'body' => 'Body'],
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['token' => ['is missing']], $this->errors());
    }

    public function testItRejectsABlankTitle(): void
    {
        $token = $this->register('jake');

        $this->json('POST', '/api/articles', [
            'article' => ['title' => '', 'description' => 'Description', 'body' => 'Body'],
        ], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['title' => ["can't be blank"]], $this->errors());
    }

    public function testItRejectsABlankDescription(): void
    {
        $token = $this->register('jake');

        $this->json('POST', '/api/articles', [
            'article' => ['title' => 'Title', 'description' => '', 'body' => 'Body'],
        ], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['description' => ["can't be blank"]], $this->errors());
    }

    public function testItRejectsABlankBody(): void
    {
        $token = $this->register('jake');

        $this->json('POST', '/api/articles', [
            'article' => ['title' => 'Title', 'description' => 'Description', 'body' => ''],
        ], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['body' => ["can't be blank"]], $this->errors());
    }

    public function testItGivesADistinctSlugToEveryArticle(): void
    {
        $token = $this->register('jake');

        $first = $this->json('POST', '/api/articles', [
            'article' => ['title' => 'Same title', 'description' => 'Description', 'body' => 'Body'],
        ], $token);
        $second = $this->json('POST', '/api/articles', [
            'article' => ['title' => 'Same title', 'description' => 'Description', 'body' => 'Body'],
        ], $token);

        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($first['article']['slug'], $second['article']['slug']);
    }

    public function testItReturnsAnArticle(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $response = $this->json('GET', '/api/articles/'.$slug);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($slug, $response['article']['slug']);
        self::assertSame('You have to believe', $response['article']['body']);
    }

    public function testItRefusesAnUnknownArticle(): void
    {
        $this->json('GET', '/api/articles/nobody');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['article' => ['not found']], $this->errors());
    }

    public function testItUpdatesAnArticle(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token, 'How to train your dragon', ['dragons']);

        $response = $this->json('PUT', '/api/articles/'.$slug, [
            'article' => ['body' => 'You have to believe a lot'],
        ], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('You have to believe a lot', $response['article']['body']);
        self::assertSame($slug, $response['article']['slug']);
        self::assertSame(['dragons'], $response['article']['tagList']);
    }

    public function testItKeepsTheTagsWhenTheyAreLeftOut(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token, 'How to train your dragon', ['dragons', 'training']);

        $response = $this->json('PUT', '/api/articles/'.$slug, [
            'article' => ['body' => 'Another body'],
        ], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['dragons', 'training'], $response['article']['tagList']);
    }

    public function testItRemovesEveryTagWhenAnEmptyListIsGiven(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token, 'How to train your dragon', ['dragons']);

        $response = $this->json('PUT', '/api/articles/'.$slug, [
            'article' => ['tagList' => []],
        ], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $response['article']['tagList']);
    }

    public function testItRejectsANullTagList(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $this->json('PUT', '/api/articles/'.$slug, ['article' => ['tagList' => null]], $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRefusesToUpdateAnArticleOfSomebodyElse(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $this->register('mary');
        $mary = $this->json('POST', '/api/users/login', [
            'user' => ['email' => 'mary@example.com', 'password' => 'password123'],
        ])['user']['token'];

        $this->json('PUT', '/api/articles/'.$slug, ['article' => ['body' => 'Hijacked']], $mary);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['article' => ['forbidden']], $this->errors());
    }

    public function testItDeletesAnArticle(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $this->json('DELETE', '/api/articles/'.$slug, null, $token);

        self::assertResponseStatusCodeSame(204);

        $this->json('GET', '/api/articles/'.$slug);
        self::assertResponseStatusCodeSame(404);
    }

    public function testItFavoritesAndUnfavoritesAnArticle(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $response = $this->json('POST', '/api/articles/'.$slug.'/favorite', null, $token);

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($response['article']['favorited']);
        self::assertSame(1, $response['article']['favoritesCount']);

        $response = $this->json('DELETE', '/api/articles/'.$slug.'/favorite', null, $token);

        self::assertResponseStatusCodeSame(200);
        self::assertFalse($response['article']['favorited']);
        self::assertSame(0, $response['article']['favoritesCount']);
    }

    public function testItListsTheArticlesWithoutTheirBody(): void
    {
        $token = $this->register('jake');
        $this->createArticle($token);

        $response = $this->json('GET', '/api/articles');

        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $response['articles']);
        self::assertSame(1, $response['articlesCount']);
        self::assertArrayNotHasKey('body', $response['articles'][0]);
    }

    public function testItCountsEveryMatchingArticle(): void
    {
        $token = $this->register('jake');
        $this->createArticle($token, 'First');
        $this->createArticle($token, 'Second');

        $response = $this->json('GET', '/api/articles?limit=1');

        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $response['articles']);
        self::assertSame(2, $response['articlesCount']);
    }

    public function testItFiltersTheArticlesByTag(): void
    {
        $token = $this->register('jake');
        $this->createArticle($token, 'Tagged', ['dragons']);
        $this->createArticle($token, 'Untagged');

        $response = $this->json('GET', '/api/articles?tag=dragons');

        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $response['articles']);
        self::assertSame('Tagged', $response['articles'][0]['title']);
        self::assertSame(1, $response['articlesCount']);
    }

    public function testItFiltersTheArticlesByAuthor(): void
    {
        $this->register('jake');
        $this->register('mary');

        $mary = $this->json('POST', '/api/users/login', [
            'user' => ['email' => 'mary@example.com', 'password' => 'password123'],
        ])['user']['token'];

        $this->createArticle($mary, 'By Mary');

        $response = $this->json('GET', '/api/articles?author=mary');

        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $response['articles']);
        self::assertSame('By Mary', $response['articles'][0]['title']);
    }

    public function testItReturnsTheFeedOfTheFollowedUsers(): void
    {
        $token = $this->register('jake');
        $this->register('mary');

        $mary = $this->json('POST', '/api/users/login', [
            'user' => ['email' => 'mary@example.com', 'password' => 'password123'],
        ])['user']['token'];

        $this->createArticle($mary, 'By Mary');

        $response = $this->json('GET', '/api/articles/feed', null, $token);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(0, $response['articles']);
        self::assertSame(0, $response['articlesCount']);

        $this->json('POST', '/api/profiles/mary/follow', null, $token);

        $response = $this->json('GET', '/api/articles/feed', null, $token);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $response['articles']);
        self::assertSame(1, $response['articlesCount']);
        self::assertTrue($response['articles'][0]['author']['following']);
    }

    public function testItRefusesTheFeedWithoutAToken(): void
    {
        $this->json('GET', '/api/articles/feed');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['token' => ['is missing']], $this->errors());
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(): array
    {
        $content = $this->client->getResponse()->getContent();

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR)['errors'];
    }
}

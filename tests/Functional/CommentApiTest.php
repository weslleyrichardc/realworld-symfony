<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class CommentApiTest extends ApiTestCase
{
    public function testItAddsAComment(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $response = $this->json('POST', '/api/articles/'.$slug.'/comments', [
            'comment' => ['body' => 'His name was my name too.'],
        ], $token);

        self::assertResponseStatusCodeSame(201);
        self::assertIsInt($response['comment']['id']);
        self::assertSame('His name was my name too.', $response['comment']['body']);
        self::assertSame('jake', $response['comment']['author']['username']);
    }

    public function testItRejectsABlankComment(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $this->json('POST', '/api/articles/'.$slug.'/comments', [
            'comment' => ['body' => ''],
        ], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['body' => ["can't be blank"]], $this->errors());
    }

    public function testItRefusesToCommentWithoutAToken(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $this->json('POST', '/api/articles/'.$slug.'/comments', [
            'comment' => ['body' => 'Hello'],
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['token' => ['is missing']], $this->errors());
    }

    public function testItRefusesToCommentOnAnUnknownArticle(): void
    {
        $token = $this->register('jake');

        $this->json('POST', '/api/articles/nobody/comments', [
            'comment' => ['body' => 'Hello'],
        ], $token);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['article' => ['not found']], $this->errors());
    }

    public function testItListsTheCommentsOfAnArticle(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $this->json('POST', '/api/articles/'.$slug.'/comments', [
            'comment' => ['body' => 'First comment'],
        ], $token);
        $this->json('POST', '/api/articles/'.$slug.'/comments', [
            'comment' => ['body' => 'Second comment'],
        ], $token);

        $response = $this->json('GET', '/api/articles/'.$slug.'/comments');

        self::assertResponseStatusCodeSame(200);
        self::assertCount(2, $response['comments']);
        self::assertSame('First comment', $response['comments'][0]['body']);
        self::assertSame('Second comment', $response['comments'][1]['body']);
    }

    public function testItDeletesAComment(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $id = $this->json('POST', '/api/articles/'.$slug.'/comments', [
            'comment' => ['body' => 'First comment'],
        ], $token)['comment']['id'];

        $this->json('DELETE', '/api/articles/'.$slug.'/comments/'.$id, null, $token);

        self::assertResponseStatusCodeSame(204);

        $response = $this->json('GET', '/api/articles/'.$slug.'/comments');
        self::assertCount(0, $response['comments']);
    }

    public function testItRefusesToDeleteACommentOfSomebodyElse(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $id = $this->json('POST', '/api/articles/'.$slug.'/comments', [
            'comment' => ['body' => 'First comment'],
        ], $token)['comment']['id'];

        $this->register('mary');
        $mary = $this->json('POST', '/api/users/login', [
            'user' => ['email' => 'mary@example.com', 'password' => 'password123'],
        ])['user']['token'];

        $this->json('DELETE', '/api/articles/'.$slug.'/comments/'.$id, null, $mary);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['comment' => ['forbidden']], $this->errors());
    }

    public function testItRefusesToDeleteAnUnknownComment(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token);

        $this->json('DELETE', '/api/articles/'.$slug.'/comments/99999', null, $token);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['comment' => ['not found']], $this->errors());
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

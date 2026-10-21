<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class TagApiTest extends ApiTestCase
{
    public function testItListsTheTagsOfTheArticles(): void
    {
        $token = $this->register('jake');
        $this->createArticle($token, 'How to train your dragon', ['dragons', 'training']);

        $response = $this->json('GET', '/api/tags');

        self::assertResponseStatusCodeSame(200);
        self::assertContains('dragons', $response['tags']);
        self::assertContains('training', $response['tags']);
    }

    public function testItDropsTheTagsOfADeletedArticle(): void
    {
        $token = $this->register('jake');
        $slug = $this->createArticle($token, 'How to train your dragon', ['dragons']);

        $this->json('DELETE', '/api/articles/'.$slug, null, $token);

        $response = $this->json('GET', '/api/tags');

        self::assertResponseStatusCodeSame(200);
        self::assertNotContains('dragons', $response['tags']);
    }
}

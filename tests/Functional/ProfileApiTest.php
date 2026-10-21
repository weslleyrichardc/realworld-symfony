<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ProfileApiTest extends ApiTestCase
{
    public function testItReturnsAProfile(): void
    {
        $this->register('jake');

        $response = $this->json('GET', '/api/profiles/jake');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('jake', $response['profile']['username']);
        self::assertNull($response['profile']['bio']);
        self::assertNull($response['profile']['image']);
        self::assertFalse($response['profile']['following']);
    }

    public function testItRefusesAnUnknownProfile(): void
    {
        $this->json('GET', '/api/profiles/nobody');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['profile' => ['not found']], $this->errors());
    }

    public function testItFollowsAndUnfollowsAProfile(): void
    {
        $token = $this->register('jake');
        $this->register('mary');

        $response = $this->json('POST', '/api/profiles/mary/follow', null, $token);

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($response['profile']['following']);

        $response = $this->json('GET', '/api/profiles/mary', null, $token);
        self::assertTrue($response['profile']['following']);

        $response = $this->json('DELETE', '/api/profiles/mary/follow', null, $token);

        self::assertResponseStatusCodeSame(200);
        self::assertFalse($response['profile']['following']);
    }

    public function testItRefusesToFollowWithoutAToken(): void
    {
        $this->register('mary');

        $this->json('POST', '/api/profiles/mary/follow');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['token' => ['is missing']], $this->errors());
    }

    public function testItRefusesToFollowAnUnknownProfile(): void
    {
        $token = $this->register('jake');

        $this->json('POST', '/api/profiles/nobody/follow', null, $token);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['profile' => ['not found']], $this->errors());
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

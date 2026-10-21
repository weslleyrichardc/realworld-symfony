<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class UserApiTest extends ApiTestCase
{
    public function testItRegistersAnAccount(): void
    {
        $response = $this->json('POST', '/api/users', [
            'user' => [
                'username' => 'jake',
                'email' => 'jake@example.com',
                'password' => 'password123',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('jake', $response['user']['username']);
        self::assertSame('jake@example.com', $response['user']['email']);
        self::assertNull($response['user']['bio']);
        self::assertNull($response['user']['image']);
        self::assertNotEmpty($response['user']['token']);
    }

    public function testItRejectsABlankUsername(): void
    {
        $this->json('POST', '/api/users', [
            'user' => ['username' => '', 'email' => 'jake@example.com', 'password' => 'password123'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['username' => ["can't be blank"]], $this->errors());
    }

    public function testItRejectsABlankPassword(): void
    {
        $this->json('POST', '/api/users', [
            'user' => ['username' => 'jake', 'email' => 'jake@example.com', 'password' => ''],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['password' => ["can't be blank"]], $this->errors());
    }

    public function testItRejectsATooShortPassword(): void
    {
        $this->json('POST', '/api/users', [
            'user' => ['username' => 'jake', 'email' => 'jake@example.com', 'password' => 'short7c'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['password' => ['is too short']], $this->errors());
    }

    public function testItRejectsADuplicateUsername(): void
    {
        $this->register('jake');

        $this->json('POST', '/api/users', [
            'user' => ['username' => 'jake', 'email' => 'other@example.com', 'password' => 'password123'],
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['username' => ['has already been taken']], $this->errors());
    }

    public function testItRejectsADuplicateEmail(): void
    {
        $this->register('jake');

        $this->json('POST', '/api/users', [
            'user' => ['username' => 'other', 'email' => 'jake@example.com', 'password' => 'password123'],
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['email' => ['has already been taken']], $this->errors());
    }

    public function testItLogsIn(): void
    {
        $this->register('jake');

        $response = $this->json('POST', '/api/users/login', [
            'user' => ['email' => 'jake@example.com', 'password' => 'password123'],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('jake', $response['user']['username']);
        self::assertNotEmpty($response['user']['token']);
    }

    public function testItRejectsWrongCredentials(): void
    {
        $this->register('jake');

        $this->json('POST', '/api/users/login', [
            'user' => ['email' => 'jake@example.com', 'password' => 'wrongpassword'],
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['credentials' => ['invalid']], $this->errors());
    }

    public function testItRefusesTheCurrentUserWithoutAToken(): void
    {
        $this->json('GET', '/api/user');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['token' => ['is missing']], $this->errors());
    }

    public function testItReturnsTheCurrentUser(): void
    {
        $token = $this->register('jake');

        $response = $this->json('GET', '/api/user', null, $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('jake', $response['user']['username']);
        self::assertNotEmpty($response['user']['token']);
    }

    public function testItUpdatesTheCurrentUser(): void
    {
        $token = $this->register('jake');

        $response = $this->json('PUT', '/api/user', [
            'user' => ['bio' => 'I like to skateboard'],
        ], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('I like to skateboard', $response['user']['bio']);
        self::assertSame('jake', $response['user']['username']);
    }

    public function testItNormalisesAnEmptyBioToNull(): void
    {
        $token = $this->register('jake');

        $response = $this->json('PUT', '/api/user', ['user' => ['bio' => '']], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertNull($response['user']['bio']);
    }

    public function testItClearsTheBioWhenItIsSetToNull(): void
    {
        $token = $this->register('jake');

        $this->json('PUT', '/api/user', ['user' => ['bio' => 'I like to skateboard']], $token);
        $response = $this->json('PUT', '/api/user', ['user' => ['bio' => null]], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertNull($response['user']['bio']);
    }

    public function testItKeepsTheFieldsThatAreLeftOut(): void
    {
        $token = $this->register('jake');

        $this->json('PUT', '/api/user', ['user' => ['bio' => 'I like to skateboard']], $token);
        $response = $this->json('PUT', '/api/user', ['user' => ['image' => 'https://example.com/a.jpg']], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('I like to skateboard', $response['user']['bio']);
        self::assertSame('https://example.com/a.jpg', $response['user']['image']);
    }

    public function testItRejectsAnEmptyUsernameOnUpdate(): void
    {
        $token = $this->register('jake');

        $this->json('PUT', '/api/user', ['user' => ['username' => '']], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['username' => ["can't be blank"]], $this->errors());
    }

    public function testItRejectsANullUsernameOnUpdate(): void
    {
        $token = $this->register('jake');

        $this->json('PUT', '/api/user', ['user' => ['username' => null]], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['username' => ["can't be blank"]], $this->errors());
    }

    public function testItAcceptsALongPassword(): void
    {
        $token = $this->register('jake');

        $this->json('PUT', '/api/user', ['user' => ['password' => str_repeat('a', 64)]], $token);

        self::assertResponseStatusCodeSame(200);
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

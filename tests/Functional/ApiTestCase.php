<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The helpers shared by the functional tests.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->resetDatabase();
    }

    /**
     * Empties the tables so that every test starts from a blank slate.
     */
    private function resetDatabase(): void
    {
        $connection = $this->entityManager->getConnection();

        $tables = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $tables[] = $metadata->getTableName();
        }

        $connection->executeStatement('TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
    }

    /**
     * Sends a JSON request and returns the decoded response.
     *
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     */
    protected function json(string $method, string $uri, ?array $payload = null, ?string $token = null): array
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Token '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $headers,
            content: null === $payload ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $content = $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Registers an account and returns its token.
     */
    protected function register(string $username = 'jake', string $password = 'password123'): string
    {
        $response = $this->json('POST', '/api/users', [
            'user' => [
                'username' => $username,
                'email' => $username.'@example.com',
                'password' => $password,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        return $response['user']['token'];
    }

    /**
     * Creates an article and returns its slug.
     */
    protected function createArticle(string $token, string $title = 'How to train your dragon', array $tags = []): string
    {
        $response = $this->json('POST', '/api/articles', [
            'article' => [
                'title' => $title,
                'description' => 'Ever wonder how?',
                'body' => 'You have to believe',
                'tagList' => $tags,
            ],
        ], $token);

        self::assertResponseStatusCodeSame(201);

        return $response['article']['slug'];
    }
}

<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * An error that is rendered with the envelope described by the RealWorld
 * specification: `{"errors": {"<resource>": ["<message>"]}}`.
 */
final class ApiException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $errors,
        ?string $message = null,
    ) {
        parent::__construct($message ?? self::defaultMessage($statusCode));
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function notFound(string $resource): self
    {
        return new self(404, [$resource => ['not found']]);
    }

    public static function forbidden(string $resource): self
    {
        return new self(403, [$resource => ['forbidden']]);
    }

    public static function unauthorized(string $reason = 'is missing'): self
    {
        return new self(401, ['token' => [$reason]]);
    }

    public static function invalidCredentials(): self
    {
        return new self(401, ['credentials' => ['invalid']]);
    }

    public static function conflict(string $field): self
    {
        return new self(409, [$field => ['has already been taken']]);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function unprocessable(array $errors): self
    {
        return new self(422, $errors);
    }

    private static function defaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'Authentication required.',
            403 => 'Access denied.',
            404 => 'Resource not found.',
            409 => 'Conflict.',
            422 => 'Unprocessable entity.',
            default => 'Error.',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pulls the JWT out of the incoming request.
 *
 * The RealWorld spec sends the token in the `Authorization` header using the
 * `Token` scheme (`Authorization: Token <jwt>`). The `Bearer` scheme defined by
 * RFC 6750 and a standalone `Token` header are accepted as well so that
 * off-the-shelf HTTP clients keep working.
 */
final class RealWorldTokenExtractor implements TokenExtractorInterface
{
    /**
     * @param list<string>   $prefixes       accepted `Authorization` schemes
     * @param string         $headerName     header carrying the token
     * @param string|null    $tokenHeaderName dedicated header carrying a bare token
     */
    public function __construct(
        private readonly string $headerName = 'Authorization',
        private readonly array $prefixes = ['Token', 'Bearer'],
        private readonly ?string $tokenHeaderName = 'Token',
    ) {
    }

    public function extract(Request $request): string|false
    {
        $header = $request->headers->get($this->headerName);
        if (null !== $header && '' !== trim($header)) {
            $token = $this->extractFromValue(trim($header));
            if (false !== $token) {
                return $token;
            }
        }

        if (null !== $this->tokenHeaderName) {
            $token = $request->headers->get($this->tokenHeaderName);
            if (null !== $token && '' !== trim($token)) {
                return trim($token);
            }
        }

        return false;
    }

    /**
     * Accepts both `<scheme> <jwt>` and a bare `<jwt>`.
     */
    private function extractFromValue(string $value): string|false
    {
        $parts = preg_split('/\s+/', $value, 2);
        if (false === $parts || [] === $parts) {
            return false;
        }

        if (2 === count($parts)) {
            foreach ($this->prefixes as $prefix) {
                if (strcasecmp($prefix, $parts[0]) === 0) {
                    return $parts[1];
                }
            }

            return false;
        }

        // A bare token: only accept it when it actually looks like a JWT.
        return str_contains($parts[0], '.') ? $parts[0] : false;
    }
}

<?php

declare(strict_types=1);

namespace App\Presenter;

use App\Entity\User;

/**
 * Renders the authenticated user, including the JWT used to call the API.
 */
final class UserPresenter
{
    /**
     * @return array{email: string, token: string, username: string, bio: string|null, image: string|null}
     */
    public function present(User $user, string $token): array
    {
        return [
            'email' => $user->getEmail(),
            'token' => $token,
            'username' => $user->getUsername(),
            'bio' => $user->getBio(),
            'image' => $user->getImage(),
        ];
    }
}

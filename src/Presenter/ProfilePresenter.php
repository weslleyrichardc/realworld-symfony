<?php

declare(strict_types=1);

namespace App\Presenter;

use App\Entity\User;

/**
 * Renders the profile of a user.
 */
final class ProfilePresenter
{
    /**
     * @param array<string, true> $followedUsernames the users followed by the viewer
     *
     * @return array{username: string, bio: string|null, image: string|null, following: bool}
     */
    public static function present(User $user, array $followedUsernames): array
    {
        return [
            'username' => $user->getUsername(),
            'bio' => $user->getBio(),
            'image' => $user->getImage(),
            'following' => isset($followedUsernames[$user->getUsername()]),
        ];
    }
}

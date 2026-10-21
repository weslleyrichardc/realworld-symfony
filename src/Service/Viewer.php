<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\User;
use App\Repository\ArticleRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Exposes the state of the authenticated user to the controllers.
 *
 * Several endpoints are readable without a token but include viewer specific
 * information such as `favorited` or `following`. This service answers those
 * questions while staying quiet for anonymous visitors.
 */
final class Viewer
{
    public function __construct(
        private readonly Security $security,
        private readonly ArticleRepository $articles,
    ) {
    }

    public function user(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->user();
    }

    /**
     * The usernames of the users followed by the viewer, keyed by username.
     *
     * @return array<string, true>
     */
    public function followedUsernames(): array
    {
        $user = $this->user();
        if (null === $user) {
            return [];
        }

        $usernames = [];
        foreach ($user->getFollowing() as $followed) {
            $usernames[$followed->getUsername()] = true;
        }

        return $usernames;
    }

    /**
     * The ids of the given articles that the viewer has favorited.
     *
     * @param list<Article> $articles
     *
     * @return array<string, true>
     */
    public function favoritedIds(array $articles): array
    {
        $user = $this->user();
        if (null === $user) {
            return [];
        }

        return $this->articles->findFavoritedIds($user, $articles);
    }
}

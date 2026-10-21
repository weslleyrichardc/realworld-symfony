<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\ApiException;
use App\Presenter\ProfilePresenter;
use App\Repository\UserRepository;
use App\Service\Viewer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoints of the profile resource.
 */
final class ProfileController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly Viewer $viewer,
    ) {
    }

    /**
     * Returns the profile of a user.
     */
    #[Route('/api/profiles/{username}', name: 'api_profile_show', methods: ['GET'])]
    public function show(string $username): JsonResponse
    {
        $profile = $this->findProfile($username);

        return new JsonResponse(['profile' => $this->present($profile)]);
    }

    /**
     * Follows a user.
     */
    #[Route('/api/profiles/{username}/follow', name: 'api_profile_follow', methods: ['POST'])]
    public function follow(string $username): JsonResponse
    {
        $viewer = $this->requireUser();
        $profile = $this->findProfile($username);

        $viewer->follow($profile);
        $this->entityManager->flush();

        return new JsonResponse(['profile' => $this->present($profile)]);
    }

    /**
     * Stops following a user.
     */
    #[Route('/api/profiles/{username}/follow', name: 'api_profile_unfollow', methods: ['DELETE'])]
    public function unfollow(string $username): JsonResponse
    {
        $viewer = $this->requireUser();
        $profile = $this->findProfile($username);

        $viewer->unfollow($profile);
        $this->entityManager->flush();

        return new JsonResponse(['profile' => $this->present($profile)]);
    }

    /**
     * @throws ApiException when no such user exists
     */
    private function findProfile(string $username): User
    {
        $profile = $this->users->findOneByUsername($username);

        if (null === $profile) {
            throw ApiException::notFound('profile');
        }

        return $profile;
    }

    /**
     * @throws ApiException when the request carries no valid token
     */
    private function requireUser(): User
    {
        $user = $this->viewer->user();

        if (null === $user) {
            throw ApiException::unauthorized();
        }

        return $user;
    }

    /**
     * @return array{username: string, bio: string|null, image: string|null, following: bool}
     */
    private function present(User $profile): array
    {
        return ProfilePresenter::present($profile, $this->viewer->followedUsernames());
    }
}

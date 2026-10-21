<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The write side of the user resource.
 */
final class UserService
{
    /**
     * The password policy follows NIST 800-63B: at least eight characters, no
     * composition rules and no breach check.
     */
    public const PASSWORD_MIN_LENGTH = 8;
    public const PASSWORD_MAX_LENGTH = 72;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    /**
     * Creates an account and returns it.
     *
     * @throws ApiException when the username or the email is already taken
     */
    public function register(string $username, string $email, string $password): User
    {
        if ($this->users->isUsernameTaken($username)) {
            throw ApiException::conflict('username');
        }

        if ($this->users->isEmailTaken($email)) {
            throw ApiException::conflict('email');
        }

        $user = new User($username, $email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Checks the credentials of an existing account.
     *
     * @throws ApiException when the credentials do not match
     */
    public function authenticate(string $email, string $password): User
    {
        $user = $this->users->findOneByEmail($email);

        if (null === $user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw ApiException::invalidCredentials();
        }

        return $user;
    }

    /**
     * Updates an account.
     *
     * Only the keys present in `$changes` are applied, so that a field left
     * out of the request keeps its value while a field explicitly set to
     * `null` is cleared.
     *
     * @param array{username?: string, email?: string, password?: string, bio?: string|null, image?: string|null} $changes
     *
     * @throws ApiException when the username or the email is already taken
     */
    public function update(User $user, array $changes): User
    {
        $username = $changes['username'] ?? null;
        $email = $changes['email'] ?? null;
        $password = $changes['password'] ?? null;

        if (null !== $username && $username !== $user->getUsername()) {
            if ($this->users->isUsernameTaken($username, $user)) {
                throw ApiException::conflict('username');
            }

            $user->setUsername($username);
        }

        if (null !== $email && $email !== $user->getEmail()) {
            if ($this->users->isEmailTaken($email, $user)) {
                throw ApiException::conflict('email');
            }

            $user->setEmail($email);
        }

        if (null !== $password) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }

        if (array_key_exists('bio', $changes)) {
            $user->setBio($changes['bio']);
        }

        if (array_key_exists('image', $changes)) {
            $user->setImage($changes['image']);
        }

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Issues a fresh JWT for the given account.
     */
    public function token(User $user): string
    {
        return $this->jwtManager->create($user);
    }
}

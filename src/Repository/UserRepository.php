<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Tells whether the username is already taken by another account.
     */
    public function isUsernameTaken(string $username, ?User $except = null): bool
    {
        return null !== $this->findOneByUsernameExcept($username, $except);
    }

    /**
     * Tells whether the email is already taken by another account.
     */
    public function isEmailTaken(string $email, ?User $except = null): bool
    {
        return null !== $this->findOneByEmailExcept($email, $except);
    }

    private function findOneByUsernameExcept(string $username, ?User $except): ?User
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.username = :username')
            ->setParameter('username', $username);

        if (null !== $except) {
            $qb->andWhere('u.id != :id')->setParameter('id', $except->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function findOneByEmailExcept(string $email, ?User $except): ?User
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email);

        if (null !== $except) {
            $qb->andWhere('u.id != :id')->setParameter('id', $except->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}

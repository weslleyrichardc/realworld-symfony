<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Returns the names of every tag that is still attached to an article.
     *
     * @return list<string>
     */
    public function findUsedNames(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.name')
            ->distinct()
            ->join('t.articles', 'a')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'name');
    }

    /**
     * Returns the tags that are no longer attached to any article.
     *
     * @return list<Tag>
     */
    public function findOrphans(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.articles IS EMPTY')
            ->getQuery()
            ->getResult();
    }
}

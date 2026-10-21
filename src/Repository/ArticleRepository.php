<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\User;
use App\Exception\ApiException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findOneBySlug(string $slug): ?Article
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Looks a slug up and gives up with the error envelope of the
     * specification when the article does not exist.
     *
     * @throws ApiException
     */
    public function findOneBySlugOrFail(string $slug): Article
    {
        $article = $this->findOneBySlug($slug);

        if (null === $article) {
            throw ApiException::notFound('article');
        }

        return $article;
    }

    /**
     * Looks a slug up while ignoring one article, so that an article may keep
     * its own slug when it is renamed.
     */
    public function findOneBySlugExcept(string $slug, ?Article $except): ?Article
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.slug = :slug')
            ->setParameter('slug', $slug);

        if (null !== $except) {
            $qb->andWhere('a.id != :id')->setParameter('id', $except->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Lists the most recent articles matching the given filters.
     *
     * @return list<Article>
     */
    public function findFiltered(?string $tag, ?string $author, ?string $favorited, int $limit, int $offset): array
    {
        return $this->createFilteredQueryBuilder($tag, $author, $favorited)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts every article matching the given filters, ignoring pagination.
     */
    public function countFiltered(?string $tag, ?string $author, ?string $favorited): int
    {
        return (int) $this->createFilteredQueryBuilder($tag, $author, $favorited)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Lists the most recent articles written by the users the given user follows.
     *
     * @return list<Article>
     */
    public function findFeed(User $user, int $limit, int $offset): array
    {
        $followed = $user->getFollowing()->toArray();
        if ([] === $followed) {
            return [];
        }

        return $this->createFeedQueryBuilder($user)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts every article written by the users the given user follows.
     */
    public function countFeed(User $user): int
    {
        if ([] === $user->getFollowing()->toArray()) {
            return 0;
        }

        return (int) $this->createFeedQueryBuilder($user)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isFavoritedBy(Article $article, User $user): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(f.id)')
            ->join('a.favoritedBy', 'f')
            ->where('a = :article')
            ->andWhere('f = :user')
            ->setParameter('article', $article)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Returns the ids of the given articles that the user has favorited.
     *
     * @param list<Article> $articles
     *
     * @return array<string, true> map of article id (RFC 4122) to true
     */
    public function findFavoritedIds(User $user, array $articles): array
    {
        if ([] === $articles) {
            return [];
        }

        // Only the root alias is selected: a to-many join would otherwise be
        // hydrated into the root entity instead of being returned as a second
        // column.
        $rows = $this->createQueryBuilder('a')
            ->select('a')
            ->join('a.favoritedBy', 'f')
            ->where('f = :user')
            ->andWhere('a IN (:articles)')
            ->setParameter('user', $user)
            ->setParameter('articles', $articles)
            ->getQuery()
            ->getResult();

        $ids = [];
        foreach ($rows as $article) {
            $ids[$article->getId()->toRfc4122()] = true;
        }

        return $ids;
    }

    /**
     * Returns the tag names of the given articles, keyed by article id.
     *
     * @param list<Article> $articles
     *
     * @return array<string, list<string>>
     */
    public function findTagNamesByArticles(array $articles): array
    {
        if ([] === $articles) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('a')
            ->join('a.tags', 't')
            ->where('a IN (:articles)')
            ->setParameter('articles', $articles)
            ->getQuery()
            ->getResult();

        $names = [];
        foreach ($rows as $article) {
            $names[$article->getId()->toRfc4122()] = $article->getTagList();
        }

        return $names;
    }

    private function createFilteredQueryBuilder(?string $tag, ?string $author, ?string $favorited): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')->join('a.author', 'u');

        // The filters are expressed as EXISTS sub-queries so that joining a
        // collection never multiplies the rows and skews the pagination.
        if (null !== $tag) {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\Article ta JOIN ta.tags tt WHERE ta = a AND tt.name = :tag)')
                ->setParameter('tag', $tag);
        }

        if (null !== $author) {
            $qb->andWhere('u.username = :author')->setParameter('author', $author);
        }

        if (null !== $favorited) {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\Article fa JOIN fa.favoritedBy fu WHERE fa = a AND fu.username = :favorited)')
                ->setParameter('favorited', $favorited);
        }

        return $qb;
    }

    private function createFeedQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->join('a.author', 'u')
            ->andWhere('u IN (:followed)')
            ->setParameter('followed', $user->getFollowing()->toArray());
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The write side of the article resource.
 */
final class ArticleService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleRepository $articles,
        private readonly TagRepository $tags,
        private readonly SlugGenerator $slugs,
    ) {
    }

    /**
     * @param list<string>|null $tagList
     */
    public function create(string $title, string $description, string $body, ?array $tagList, User $author): Article
    {
        $article = new Article($title, $this->slugs->generate($title), $description, $body, $author);

        if (null !== $tagList) {
            $article->setTags($this->resolveTags($tagList));
        }

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return $article;
    }

    /**
     * Updates an article. A `null` argument means the field is left untouched.
     *
     * @param list<string>|null $tagList
     */
    public function update(Article $article, ?string $title, ?string $description, ?string $body, ?array $tagList): Article
    {
        if (null !== $title) {
            $article->setTitle($title);
            $article->setSlug($this->slugs->generate($title, $article));
        }

        if (null !== $description) {
            $article->setDescription($description);
        }

        if (null !== $body) {
            $article->setBody($body);
        }

        if (null !== $tagList) {
            $article->setTags($this->resolveTags($tagList));
        }

        $this->entityManager->flush();
        $this->removeOrphanTags();

        return $article;
    }

    public function delete(Article $article): void
    {
        $this->entityManager->remove($article);
        $this->entityManager->flush();
        $this->removeOrphanTags();
    }

    public function favorite(Article $article, User $user): void
    {
        if ($this->articles->isFavoritedBy($article, $user)) {
            return;
        }

        $article->addFavorite($user);
        $this->entityManager->flush();
    }

    public function unfavorite(Article $article, User $user): void
    {
        if (!$this->articles->isFavoritedBy($article, $user)) {
            return;
        }

        $article->removeFavorite($user);
        $this->entityManager->flush();
    }

    /**
     * Finds the tags with the given names, creating the missing ones.
     *
     * @param list<string> $names
     *
     * @return list<Tag>
     */
    private function resolveTags(array $names): array
    {
        $tags = [];

        foreach (array_unique($names) as $name) {
            $tag = $this->tags->findOneBy(['name' => $name]);

            if (null === $tag) {
                $tag = new Tag($name);
                $this->entityManager->persist($tag);
            }

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Drops the tags that are not referenced by any article anymore.
     */
    private function removeOrphanTags(): void
    {
        $orphans = $this->tags->findOrphans();

        if ([] === $orphans) {
            return;
        }

        foreach ($orphans as $orphan) {
            $this->entityManager->remove($orphan);
        }

        $this->entityManager->flush();
    }
}

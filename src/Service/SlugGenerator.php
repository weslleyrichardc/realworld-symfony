<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Derives the URL identifier of an article from its title.
 *
 * Two articles may share the same title, so the slug is made unique by
 * appending a counter.
 */
final class SlugGenerator
{
    private readonly AsciiSlugger $slugger;

    public function __construct(private readonly ArticleRepository $articles)
    {
        $this->slugger = new AsciiSlugger();
    }

    /**
     * Generates a slug for the given title that is not used by another
     * article yet.
     *
     * @param Article|null $except the article being renamed, if any
     */
    public function generate(string $title, ?Article $except = null): string
    {
        $base = $this->slugger->slug($title)->lower()->toString();
        if ('' === $base) {
            $base = 'article';
        }

        $slug = $base;
        $suffix = 1;
        while (null !== $this->articles->findOneBySlugExcept($slug, $except)) {
            ++$suffix;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}

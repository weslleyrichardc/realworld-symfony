<?php

declare(strict_types=1);

namespace App\Presenter;

use App\Entity\Article;

/**
 * Renders an article.
 *
 * Listings omit the body: since 2024/08/16 the specification no longer
 * returns it to keep the payloads small.
 */
final class ArticlePresenter
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * @param array<string, true> $favoritedIds     ids of the articles favorited by the viewer
     * @param array<string, true> $followedUsernames the users followed by the viewer
     * @param list<string>|null   $tagNames         the tags of the article, when pre-loaded
     */
    public function present(
        Article $article,
        array $favoritedIds,
        array $followedUsernames,
        ?array $tagNames = null,
        bool $withBody = true,
    ): array {
        $data = [
            'slug' => $article->getSlug(),
            'title' => $article->getTitle(),
            'description' => $article->getDescription(),
        ];

        if ($withBody) {
            $data['body'] = $article->getBody();
        }

        $data['tagList'] = $tagNames ?? $article->getTagList();
        $data['createdAt'] = $article->getCreatedAt()->format(self::DATE_FORMAT);
        $data['updatedAt'] = $article->getUpdatedAt()->format(self::DATE_FORMAT);
        $data['favorited'] = isset($favoritedIds[$article->getId()->toRfc4122()]);
        $data['favoritesCount'] = $article->getFavoritesCount();
        $data['author'] = ProfilePresenter::present($article->getAuthor(), $followedUsernames);

        return $data;
    }
}

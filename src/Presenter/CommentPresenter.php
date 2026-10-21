<?php

declare(strict_types=1);

namespace App\Presenter;

use App\Entity\Comment;

/**
 * Renders a comment.
 */
final class CommentPresenter
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * @param array<string, true> $followedUsernames the users followed by the viewer
     *
     * @return array{id: int|null, createdAt: string, updatedAt: string, body: string, author: array<string, mixed>}
     */
    public function present(Comment $comment, array $followedUsernames): array
    {
        return [
            'id' => $comment->getId(),
            'createdAt' => $comment->getCreatedAt()->format(self::DATE_FORMAT),
            'updatedAt' => $comment->getUpdatedAt()->format(self::DATE_FORMAT),
            'body' => $comment->getBody(),
            'author' => ProfilePresenter::present($comment->getAuthor(), $followedUsernames),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comment')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'datetime_immutable_microsecond', columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable_microsecond', columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    public function __construct(string $body, Article $article, User $author)
    {
        $this->body = $body;
        $this->article = $article;
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }
}

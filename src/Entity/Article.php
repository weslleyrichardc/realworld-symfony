<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'article')]
#[ORM\UniqueConstraint(name: 'UNIQ_ARTICLE_SLUG', fields: ['slug'])]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'datetime_immutable_microsecond', columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable_microsecond', columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(options: ['default' => 0])]
    private int $favoritesCount = 0;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_tag')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $tags;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'favorites')]
    #[ORM\JoinTable(name: 'article_favorite')]
    private Collection $favoritedBy;

    /**
     * The comments written on this article.
     *
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'article')]
    private Collection $comments;

    public function __construct(string $title, string $slug, string $description, string $body, User $author)
    {
        $this->id = Uuid::v7();
        $this->title = $title;
        $this->slug = $slug;
        $this->description = $description;
        $this->body = $body;
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->tags = new ArrayCollection();
        $this->favoritedBy = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
        $this->touch();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getFavoritesCount(): int
    {
        return $this->favoritesCount;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @param iterable<Tag> $tags
     */
    public function setTags(iterable $tags): void
    {
        $this->tags = new ArrayCollection();
        foreach ($tags as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }
        $this->touch();
    }

    /**
     * The names of the tags of the article, in alphabetical order.
     *
     * @return list<string>
     */
    public function getTagList(): array
    {
        $names = [];
        foreach ($this->tags as $tag) {
            $names[] = $tag->getName();
        }

        sort($names);

        return $names;
    }

    /**
     * @return Collection<int, User>
     */
    public function getFavoritedBy(): Collection
    {
        return $this->favoritedBy;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * Registers a favorite. The caller is responsible for making sure the user
     * has not already favorited the article.
     */
    public function addFavorite(User $user): void
    {
        // The collection has to be loaded before it can be modified, otherwise
        // Doctrine would not be able to tell the persisted rows from the new one.
        $this->favoritedBy->initialize();
        $this->favoritedBy->add($user);
        ++$this->favoritesCount;
    }

    /**
     * Removes a favorite. The caller is responsible for making sure the user
     * has actually favorited the article.
     */
    public function removeFavorite(User $user): void
    {
        $this->favoritedBy->initialize();
        $this->favoritedBy->removeElement($user);
        --$this->favoritesCount;
    }

    /**
     * Bumps `updatedAt`, guaranteeing it moves forward by at least a
     * millisecond so that clients can always tell two revisions apart.
     */
    private function touch(): void
    {
        $now = new \DateTimeImmutable();
        $this->updatedAt = $now > $this->updatedAt ? $now : $this->updatedAt->modify('+1 millisecond');
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A category.
 *
 * @see https://schema.org/Category
 */
#[ApiResource(
    operations: [
        new GetCollection(
            itemUriTemplate: '/admin/categories/{id}{._format}',
            paginationClientItemsPerPage: true
        ),
        new Post(
            itemUriTemplate: '/admin/categories/{id}{._format}'
        ),
        new Get(
            uriTemplate: '/admin/categories/{id}{._format}'
        ),
        new Put(
            uriTemplate: '/admin/categories/{id}{._format}'
        ),
        new Delete(
            uriTemplate: '/admin/categories/{id}{._format}'
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['Category:read:admin', 'Enum:read'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['Category:write'],
    ],
    security: 'is_granted("ROLE_ADMIN")'
)]
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[ApiProperty(identifier: true)]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(groups: ['Category:read', 'Category:write'])]
    private string $name;

    #[ORM\ManyToMany(targetEntity: Book::class, mappedBy: 'categories')]
    #[Groups(groups: ['Category:read', 'Category:write'])]
    private Collection $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(Book $book): self
    {
        if (!$this->books->contains($book)) {
            $this->books[] = $book;
            $book->addCategory($this);
        }

        return $this;
    }
}

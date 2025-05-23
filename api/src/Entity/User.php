<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Uuid;

/**
 * A person (alive, dead, undead, or fictional).
 *
 * @see https://schema.org/Person
 */
#[ApiResource(
    types: ['https://schema.org/Person'],
    operations: [
        new GetCollection(
            uriTemplate: '/admin/users{._format}',
            itemUriTemplate: '/admin/users/{id}{._format}',
            security: 'is_granted("OIDC_ADMIN")',
            filters: ['app.filter.user.admin.name'],
            paginationClientItemsPerPage: true
        ),
        new Get(
            uriTemplate: '/admin/users/{id}{._format}',
            security: 'is_granted("OIDC_ADMIN")'
        ),
        new Get(
            uriTemplate: '/users/{id}{._format}',
            security: 'object === user'
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['User:read'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
    ]
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity('email')]
class User implements UserInterface
{
    /**
     * @see https://schema.org/identifier
     */
    #[ApiProperty(types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private Uuid $id;

    /**
     * @see https://schema.org/email
     */
    #[ORM\Column(unique: true)]
    public string $email;

    /**
     * @see https://schema.org/givenName
     */
    #[ApiProperty(types: ['https://schema.org/givenName'])]
    #[Groups(groups: ['User:read', 'Review:read'])]
    #[ORM\Column]
    public string $firstName;

    /**
     * @see https://schema.org/familyName
     */
    #[ApiProperty(types: ['https://schema.org/familyName'])]
    #[Groups(groups: ['User:read', 'Review:read'])]
    #[ORM\Column]
    public string $lastName;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see https://schema.org/name
     */
    #[ApiProperty(iris: ['https://schema.org/name'])]
    #[Groups(groups: ['User:read', 'Review:read'])]
    public function getName(): string
    {
        return trim(\sprintf('%s %s', $this->firstName, $this->lastName));
    }
}

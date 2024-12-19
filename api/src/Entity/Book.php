<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use App\Enum\BookCondition;
use App\Enum\PromotionStatus;
use App\Repository\BookRepository;
use App\State\Processor\BookPersistProcessor;
use App\State\Processor\BookRemoveProcessor;
use App\Validator\BookUrl;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A book.
 *
 * @see https://schema.org/Book
 */
#[ApiResource(
    uriTemplate: '/admin/books{._format}',
    types: ['https://schema.org/Book', 'https://schema.org/Offer'],
    operations: [
        new GetCollection(
            itemUriTemplate: '/admin/books/{id}{._format}',
            paginationClientItemsPerPage: true
        ),
        new Post(
            processor: BookPersistProcessor::class,
            itemUriTemplate: '/admin/books/{id}{._format}'
        ),
        new Get(
            uriTemplate: '/admin/books/{id}{._format}'
        ),
        // https://github.com/api-platform/admin/issues/370
        new Put(
            uriTemplate: '/admin/books/{id}{._format}',
            processor: BookPersistProcessor::class
        ),
        new Delete(
            uriTemplate: '/admin/books/{id}{._format}',
            processor: BookRemoveProcessor::class
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['Book:read:admin', 'Enum:read'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['Book:write'],
    ],
    collectDenormalizationErrors: true,
    security: 'is_granted("OIDC_ADMIN")',
    mercure: [
        'topics' => [
            '@=iri(object, ' . UrlGeneratorInterface::ABS_URL . ', get_operation(object, "/admin/books/{id}{._format}"))',
            '@=iri(object, ' . UrlGeneratorInterface::ABS_URL . ', get_operation(object, "/books/{id}{._format}"))',
        ],
    ]
)]
#[ApiResource(
    types: ['https://schema.org/Book', 'https://schema.org/Offer'],
    operations: [
        new GetCollection(
            itemUriTemplate: '/books/{id}{._format}'
        ),
        new Get(),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['Book:read', 'Enum:read'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
    ],
    mercure: [
        'topics' => [
            '@=iri(object, ' . UrlGeneratorInterface::ABS_URL . ', get_operation(object, "/admin/books/{id}{._format}"))',
            '@=iri(object, ' . UrlGeneratorInterface::ABS_URL . ', get_operation(object, "/books/{id}{._format}"))',
        ],
    ]
)]
#[ORM\Entity(repositoryClass: BookRepository::class)]
#[UniqueEntity(fields: ['book'])]
class Book
{
    /**
     * @see https://schema.org/identifier
     */
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    private ?EntityManagerInterface $entityManager = null;
    /**
     * @see https://schema.org/url/
     */
    #[ApiProperty(types: ['https://schema.org/url'])]
    #[Groups(groups: ['Book:read', 'Book:read:admin'])]
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]+$/',
        message: 'The slug must contain only lowercase Latin letters, numbers, or hyphens.'
    )]
    private ?string $slug = null;
    /**
     * @see https://schema.org/itemOffered
     */
    #[ApiProperty(
        types: ['https://schema.org/itemOffered', 'https://purl.org/dc/terms/BibliographicResource'],
        example: 'https://openlibrary.org/books/OL2055137M.json'
    )]
    #[Assert\NotBlank(allowNull: false)]
    #[Assert\Url(protocols: ['https'], requireTld: true)]
    #[BookUrl]
    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Bookmark:read', 'Book:write'])]
    #[ORM\Column(unique: true)]
    public ?string $book = null;

    /**
     * @see https://schema.org/name
     */
    #[ApiFilter(OrderFilter::class)]
    #[ApiFilter(SearchFilter::class, strategy: 'i' . SearchFilterInterface::STRATEGY_PARTIAL)]
    #[ApiProperty(
        iris: ['https://schema.org/name'],
        example: 'Hyperion'
    )]
    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Bookmark:read', 'Review:read:admin'])]
    #[ORM\Column(type: Types::TEXT)]
    public ?string $title = null;

    /**
     * @see https://schema.org/author
     */
    #[ApiFilter(SearchFilter::class, strategy: 'i' . SearchFilterInterface::STRATEGY_PARTIAL)]
    #[ApiProperty(
        types: ['https://schema.org/author'],
        example: 'Dan Simmons'
    )]
    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Bookmark:read', 'Review:read:admin'])]
    #[ORM\Column(nullable: true)]
    public ?string $author = null;

    /**
     * @see https://schema.org/OfferItemCondition
     */
    #[ApiFilter(SearchFilter::class, strategy: SearchFilterInterface::STRATEGY_EXACT)]
    #[ApiProperty(
        types: ['https://schema.org/OfferItemCondition'],
        example: BookCondition::NewCondition->value
    )]
    #[Assert\NotNull]
    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Bookmark:read', 'Book:write'])]
    #[ORM\Column(name: '`condition`', type: 'string', enumType: BookCondition::class)]
    public ?BookCondition $condition = null;

    /**
     * An IRI of reviews.
     *
     * @var Collection<int, Review>
     *
     * @see https://schema.org/reviews
     */
    #[ApiProperty(
        types: ['https://schema.org/reviews'],
        example: '/books/6acacc80-8321-4d83-9b02-7f2c7bf6eb1d/reviews',
        uriTemplate: '/books/{bookId}/reviews{._format}'
    )]
    #[Groups(groups: ['Book:read', 'Bookmark:read'])]
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book')]
    public Collection $reviews;

    /**
     * The overall rating, based on a collection of reviews or ratings, of the item.
     *
     * @see https://schema.org/aggregateRating
     */
    #[ApiProperty(
        types: ['https://schema.org/aggregateRating'],
        example: 1
    )]
    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Bookmark:read'])]
    public ?int $rating = null;

    /**
     * @see https://schema.org/isPromoted
     */
    #[ApiProperty(
        types: ['https://schema.org/Boolean'],
        example: false
    )]
    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Book:write'])]
    #[ORM\Column(type: 'boolean')]
    public bool $isPromoted = false;

    /**
     * @see https://schema.org/promotionStatus
     */
    #[ApiProperty(
        types: ['https://schema.org/Text'],
        example: PromotionStatus::NONE->value,
        security: "is_granted('OIDC_ADMIN')",
        openapiContext: [
            'type' => 'string',
            'enum' => ['none', 'promotion', 'discount'],
            'example' => 'none'
        ]
    )]
    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Book:write'])]
    #[Assert\NotNull(message: "Promotion status is required.")]
    #[ORM\Column(type: 'string', enumType: PromotionStatus::class)]
    private PromotionStatus $promotionStatus = PromotionStatus::NONE;

    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'books')]
    #[ORM\JoinTable(name: 'book_categories')]
    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Book:write'])]
    private Collection $categories;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPromotionStatus(): PromotionStatus
    {
        return $this->promotionStatus;
    }

    public function setPromotionStatus(PromotionStatus $promotionStatus): self
    {
        $this->promotionStatus = $promotionStatus;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug($slug): self
    {
        if ($slug instanceof \Closure) {
            $slug = $slug($this);
        }

        $this->slug = (string) $slug;
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function computeSlug(): void
    {
        if (!$this->slug || 'book-' === substr($this->slug, 0, 5)) {
            $slugger = new AsciiSlugger();
            $this->slug = $this->generateUniqueSlug($slugger->slug($this->title ?? 'untitled')->lower());
        }
    }

    private function generateUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $i = 1;

        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        if (!$this->entityManager) {
            return false;
        }

        $existingBook = $this->entityManager->getRepository(self::class)->findOneBy(['slug' => $slug]);
        return $existingBook !== null && $existingBook !== $this;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): self
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    #[Groups(groups: ['Book:read', 'Book:read:admin'])]
    public function getCategories(): Collection
    {
        return $this->categories ?? new ArrayCollection();
    }

    #[Groups(groups: ['Book:read', 'Book:read:admin', 'Book:write'])]
    public function setCategories(iterable $categories): self
    {
        $this->categories = new ArrayCollection();
        if ($categories !== null) {
            foreach ($categories as $category) {
                if (is_string($category) && str_starts_with($category, '/api/categories/')) {
                    $categoryId = substr($category, strlen('/api/categories/'));
                    $category = $this->entityManager->getReference(Category::class, $categoryId);
                }
                $this->addCategory($category);
            }
        }
        return $this;
    }
    public function addCategory(Category $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories[] = $category;
            $category->addBook($this);
        }
        return $this;
    }

    public function removeCategory(Category $category): self
    {
        if ($this->categories->removeElement($category)) {
            $category->removeBook($this);
        }
        return $this;
    }
}
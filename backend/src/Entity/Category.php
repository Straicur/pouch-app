<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Self-referencing tree node. Deleting a category cascades to its descendants
 * at the database level (see the parent join column's onDelete).
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'category')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'category_id', type: Types::INTEGER, unique: true, nullable: false, options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, nullable: false)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'category_id', nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /**
     * Bcrypt hash of the category's own access key (Part 7). Null means "no
     * key of its own" — not "unprotected": see CategoryRepository/CategoryService
     * for walking up to the nearest ancestor that does have one (inherited key).
     */
    #[ORM\Column(name: 'access_key_hash', type: Types::STRING, length: 255, nullable: true)]
    private ?string $accessKeyHash = null;

    /**
     * Bumped every time setAccessKeyHash() runs — folded into the signed
     * resource string a Part 7 access grant is issued for (AccessKeyResource),
     * so resetting/removing/changing the key invalidates every grant already
     * handed out for the old one, without needing a revocation list. See
     * AccessKeyGuard/AccessKeyService.
     */
    #[ORM\Column(name: 'access_key_version', type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    private int $accessKeyVersion = 0;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    public function __construct(string $name, ?self $parent = null)
    {
        $this->name = $name;
        $this->parent = $parent;
        $this->children = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getAccessKeyHash(): ?string
    {
        return $this->accessKeyHash;
    }

    public function setAccessKeyHash(?string $accessKeyHash): static
    {
        $this->accessKeyHash = $accessKeyHash;
        ++$this->accessKeyVersion;

        return $this;
    }

    public function getAccessKeyVersion(): int
    {
        return $this->accessKeyVersion;
    }
}

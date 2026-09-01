<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\TagRepository;
use App\Services\Pouch\PouchAware;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
#[ORM\UniqueConstraint(name: 'uniq_tag_name_pouch', columns: ['name', 'pouch_id'])]
class Tag implements PouchAware
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'tag_id', type: Types::INTEGER, unique: true, nullable: false, options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 50, nullable: false)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Pouch::class)]
    #[ORM\JoinColumn(name: 'pouch_id', referencedColumnName: 'pouch_id', nullable: false)]
    private Pouch $pouch;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name, Pouch $pouch)
    {
        $this->name = $name;
        $this->pouch = $pouch;
        $this->createdAt = new DateTimeImmutable();
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

    #[Override]
    public function getPouch(): Pouch
    {
        return $this->pouch;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

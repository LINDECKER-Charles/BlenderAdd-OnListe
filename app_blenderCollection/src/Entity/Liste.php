<?php

namespace App\Entity;

use App\Repository\ListeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListeRepository::class)]
class Liste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'listes')]
    #[ORM\JoinColumn(name: 'usser_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?User $usser = null;

    /**
     * @var Collection<int, Addon>
     */
    #[ORM\ManyToMany(targetEntity: Addon::class, inversedBy: 'listes')]
    private Collection $addons;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $Image = null;

    #[ORM\Column]
    private ?bool $isVisible = null;

    /**
     * @var Collection<int, Post>
     */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'commentaire', orphanRemoval: true)]
    private Collection $posts;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'favoris')]
    private Collection $users;

    #[ORM\Column(nullable: true)]
    private ?int $download = null;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->addons = new ArrayCollection();
        $this->topics = new ArrayCollection();
        $this->posts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getUsser(): ?User
    {
        return $this->usser;
    }

    public function setUsser(?User $usser): static
    {
        $this->usser = $usser;

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            $user->removeFavori($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Addon>
     */
    public function getAddons(): Collection
    {
        return $this->addons;
    }

    public function addAddon(Addon $addon): static
    {
        if (!$this->addons->contains($addon)) {
            $this->addons->add($addon);
        }

        return $this;
    }

    public function removeAddon(Addon $addon): static
    {
        $this->addons->removeElement($addon);

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->Image;
    }

    public function setImage(?string $Image): static
    {
        $this->Image = $Image;

        return $this;
    }

    public function isVisible(): ?bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): static
    {
        $this->isVisible = $isVisible;

        return $this;
    }

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setCommentaire($this);
        }

        return $this;
    }

    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            // set the owning side to null (unless already changed)
            if ($post->getCommentaire() === $this) {
                $post->setCommentaire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->addFavori($this);
        }

        return $this;
    }

    public function getFavorisCount(): int
    {
        return $this->users->count();
    }

    public function getDownload(): ?int
    {
        return $this->download;
    }

    public function setDownload(int $download): static
    {
        $this->download = $download;

        return $this;
    }
}

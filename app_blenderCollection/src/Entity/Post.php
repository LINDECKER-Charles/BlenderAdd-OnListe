<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    /**
     * @var Collection<int, SousPost>
     */
    #[ORM\OneToMany(targetEntity: SousPost::class, mappedBy: 'post', orphanRemoval: true)]
    private Collection $postSpost;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Liste $commentaire = null;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    private ?User $commenter = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'posts')]
    private Collection $liker;

    public function __construct()
    {
        $this->postSpost = new ArrayCollection();
        $this->liker = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

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

    /**
     * @return Collection<int, sousPost>
     */
    public function getPostSpost(): Collection
    {
        return $this->postSpost;
    }

    public function addPostSpost(SousPost $postSpost): static
    {
        if (!$this->postSpost->contains($postSpost)) {
            $this->postSpost->add($postSpost);
            $postSpost->setPost($this);
        }

        return $this;
    }

    public function removePostSpost(SousPost $postSpost): static
    {
        if ($this->postSpost->removeElement($postSpost)) {
            // set the owning side to null (unless already changed)
            if ($postSpost->getPost() === $this) {
                $postSpost->setPost(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?Liste
    {
        return $this->commentaire;
    }

    public function setCommentaire(?Liste $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getCommenter(): ?User
    {
        return $this->commenter;
    }

    public function setCommenter(?User $commenter): static
    {
        $this->commenter = $commenter;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getLiker(): Collection
    {
        return $this->liker;
    }

    public function addLiker(User $liker): static
    {
        if (!$this->liker->contains($liker)) {
            $this->liker->add($liker);
        }

        return $this;
    }

    public function removeLiker(User $liker): static
    {
        $this->liker->removeElement($liker);

        return $this;
    }
}

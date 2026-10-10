<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 180)]
    #[Assert\NotBlank(message: 'Please enter a username.')]
    private string $username = '';

    #[ORM\Column(type: Types::STRING, length: 180)]
    #[Assert\NotBlank(message: 'Please enter an email.')]
    #[Assert\Email]
    private string $email = '';

    #[ORM\Column(type: Types::STRING)]
    private string $password = '';

    /**
     * Transient, never persisted: used only to bind the plaintext password from admin forms.
     */
    private ?string $plainPassword = null;

    #[ORM\Column(type: Types::ARRAY)]
    private array $roles = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = true;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Please enter your name.', groups: ['Registration', 'Profile'])]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'The name is too short.',
        maxMessage: 'The name is too long.',
        groups: ['Registration', 'Profile']
    )]
    private ?string $name = null;

    #[ORM\Column(name: 'publicDisplayName', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $publicDisplayName = null;

    public function getPublicDisplayName(): ?string { return $this->publicDisplayName; }
    public function setPublicDisplayName(?string $name): static
    {
        $this->publicDisplayName = trim($name ?? '') ?: null;
        return $this;
    }
    public function getAuthorDisplayName(): string
    {
        return $this->publicDisplayName ?: ($this->name ?: $this->username);
    }

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '#^/uploads/media/(?!.*\.\.)[^\s?\#]+\.(?:jpe?g|png|gif|webp|avif)$#i', message: 'Chọn ảnh hợp lệ từ thư viện media.')]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000, maxMessage: 'Giới thiệu tối đa 10.000 ký tự.')]
    private ?string $biography = null;

    #[ORM\Column(name: 'showAuthorInfo', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $showAuthorInfo = false;

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): static { $this->avatar = $avatar ?: null; return $this; }
    public function getBiography(): ?string { return $this->biography; }
    public function setBiography(?string $biography): static { $this->biography = $biography; return $this; }
    public function isShowAuthorInfo(): bool { return $this->showAuthorInfo; }
    public function setShowAuthorInfo(bool $show): static { $this->showAuthorInfo = $show; return $this; }

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'adminNotificationRead', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $adminNotificationRead = false;

    public function getId()
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * The identifier Symfony Security uses to load/refresh this user.
     */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Required by UserInterface on Symfony <6.0; bcrypt/argon2 embed their own salt.
     */
    public function getSalt(): ?string
    {
        return null;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setAdminNotificationRead($adminNotificationRead)
    {
        $this->adminNotificationRead = (bool) $adminNotificationRead;

        return $this;
    }

    public function getAdminNotificationRead()
    {
        return $this->adminNotificationRead;
    }

    public function isAdminNotificationRead()
    {
        return $this->getAdminNotificationRead();
    }
}

<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[UniqueEntity(fields: ['authorSlug'], message: 'Đường dẫn trang tác giả này đã có người dùng.')]
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

    /**
     * Đường dẫn công khai /tac-gia/{authorSlug}/. Tách khỏi username để URL
     * không làm lộ tên đăng nhập.
     */
    #[ORM\Column(name: 'authorSlug', type: Types::STRING, length: 190, unique: true, nullable: true)]
    #[Assert\Length(max: 190)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Chỉ dùng chữ thường không dấu, số và dấu gạch ngang, ví dụ: nguyen-van-a.')]
    private ?string $authorSlug = null;

    /** Chức danh, ví dụ "Kiến trúc sư trưởng" — schema Person.jobTitle. */
    #[ORM\Column(name: 'jobTitle', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $jobTitle = null;

    /** Lĩnh vực chuyên môn, phân tách bằng dấu phẩy — schema Person.knowsAbout. */
    #[ORM\Column(name: 'knowsAbout', type: Types::STRING, length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $knowsAbout = null;

    /** Link hồ sơ ở nơi khác (Facebook, LinkedIn, YouTube...), mỗi dòng một link — schema Person.sameAs. */
    #[ORM\Column(name: 'sameAs', type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 3000)]
    private ?string $sameAs = null;

    public function getAuthorSlug(): ?string { return $this->authorSlug; }
    public function setAuthorSlug(?string $slug): static
    {
        $this->authorSlug = strtolower(trim($slug ?? '')) ?: null;
        return $this;
    }
    public function getJobTitle(): ?string { return $this->jobTitle; }
    public function setJobTitle(?string $jobTitle): static { $this->jobTitle = trim($jobTitle ?? '') ?: null; return $this; }
    public function getKnowsAbout(): ?string { return $this->knowsAbout; }
    public function setKnowsAbout(?string $knowsAbout): static { $this->knowsAbout = trim($knowsAbout ?? '') ?: null; return $this; }
    public function getSameAs(): ?string { return $this->sameAs; }
    public function setSameAs(?string $sameAs): static { $this->sameAs = trim($sameAs ?? '') ?: null; return $this; }

    /** @return string[] */
    public function getKnowsAboutList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->knowsAbout))));
    }

    /** @return string[] */
    public function getSameAsList(): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) $this->sameAs)))));
    }

    /**
     * Có trang tác giả công khai không: tài khoản đang hoạt động, bật hiển thị
     * thông tin tác giả và đã đặt đường dẫn.
     */
    public function isPublicAuthor(): bool
    {
        return $this->enabled && $this->showAuthorInfo && $this->authorSlug !== null;
    }

    #[Assert\Callback]
    public function validateAuthorProfile(ExecutionContextInterface $context): void
    {
        if ($this->authorSlug !== null && $this->authorSlug === strtolower($this->username)) {
            $context->buildViolation('Không đặt đường dẫn trang tác giả trùng tên đăng nhập.')
                ->atPath('authorSlug')->addViolation();
        }

        foreach ($this->getSameAsList() as $url) {
            if (!preg_match('#^https://[^\s/]+\.[^\s]+$#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                $context->buildViolation('Link không hợp lệ (cần bắt đầu bằng https://): {{ url }}')
                    ->setParameter('{{ url }}', $url)
                    ->atPath('sameAs')->addViolation();
            }
        }
    }

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

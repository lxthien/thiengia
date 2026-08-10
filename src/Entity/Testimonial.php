<?php

namespace App\Entity;

use App\Repository\TestimonialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'testimonial')]
#[ORM\Entity(repositoryClass: TestimonialRepository::class)]
class Testimonial
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[Assert\NotBlank(message: 'Vui lòng nhập tên khách hàng.')]
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private $name;

    #[ORM\Column(name: 'role', type: Types::STRING, length: 255, nullable: true)]
    private $role;

    /**
     * Full root-relative URL of the avatar, chosen via the Media Library picker
     * (e.g. "/uploads/media/xxx.jpg"). Not a Vich-managed field.
     */
    #[Assert\NotBlank(message: 'Vui lòng chọn ảnh đại diện từ Media Library.')]
    #[ORM\Column(name: 'avatarUrl', type: Types::STRING, length: 255)]
    private $avatarUrl;

    #[Assert\NotBlank(message: 'Vui lòng nhập nội dung đánh giá.')]
    #[ORM\Column(name: 'text', type: Types::TEXT)]
    private $text;

    #[Assert\Range(min: 1, max: 5)]
    #[ORM\Column(name: 'rating', type: Types::INTEGER, options: ['default' => 5])]
    private $rating = 5;

    #[ORM\Column(name: 'position', type: Types::INTEGER, nullable: true)]
    private $position;

    #[ORM\Column(name: 'enable', type: Types::BOOLEAN, options: ['default' => true])]
    private $enable = true;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setRole($role)
    {
        $this->role = $role;

        return $this;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function setAvatarUrl($avatarUrl)
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getAvatarUrl()
    {
        return $this->avatarUrl;
    }

    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setRating($rating)
    {
        $this->rating = $rating;

        return $this;
    }

    public function getRating()
    {
        return $this->rating;
    }

    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setEnable($enable)
    {
        $this->enable = $enable;

        return $this;
    }

    public function getEnable()
    {
        return $this->enable;
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

    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}

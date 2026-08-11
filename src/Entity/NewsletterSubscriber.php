<?php

namespace App\Entity;

use App\Repository\NewsletterSubscriberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'newsletter_subscriber')]
#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
class NewsletterSubscriber
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[Assert\NotBlank(message: 'Vui lòng nhập email.')]
    #[Assert\Email(message: 'Email không hợp lệ.')]
    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, unique: true)]
    private $email;

    #[ORM\Column(name: 'ip', type: Types::STRING, length: 255, nullable: true)]
    private $ip;

    #[ORM\Column(name: 'isRead', type: Types::BOOLEAN, options: ['default' => false])]
    private $isRead = false;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    public function getId()
    {
        return $this->id;
    }

    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;

        return $this;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function setIsRead($isRead)
    {
        $this->isRead = (bool) $isRead;

        return $this;
    }

    public function getIsRead()
    {
        return $this->isRead;
    }

    /**
     * Alias for Twig readability
     *
     * @return bool
     */
    public function isRead()
    {
        return $this->getIsRead();
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
}

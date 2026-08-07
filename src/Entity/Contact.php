<?php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'contact', options: ['collate' => 'utf8_general_ci'])]
#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private $name;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255, nullable: true)]
    private $title;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'phone', type: Types::STRING, length: 255)]
    private $phone;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, nullable: true)]
    private $email;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'contents', type: Types::TEXT)]
    private $contents;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    #[ORM\Column(name: 'isRead', type: Types::BOOLEAN, options: ['default' => true])]
    private $isRead = false;

    #[ORM\Column(name: 'gclid', type: Types::STRING, length: 255, nullable: true)]
    private $gclid;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Contact
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set title
     *
     * @param string $title
     *
     * @return Contact
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set phone
     *
     * @param string $phone
     *
     * @return Contact
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return Contact
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set contents
     *
     * @param string $contents
     *
     * @return Contact
     */
    public function setContents($contents)
    {
        $this->contents = $contents;

        return $this;
    }

    /**
     * Get contents
     *
     * @return string
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Contact
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return Contact
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set isRead
     *
     * @param bool $isRead
     *
     * @return Contact
     */
    public function setIsRead($isRead)
    {
        $this->isRead = (bool) $isRead;

        return $this;
    }

    /**
     * Get isRead
     *
     * @return bool
     */
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

    /**
     * Set gclid
     *
     * @param string $gclid
     *
     * @return Contact
     */
    public function setGclid($gclid)
    {
        $this->gclid = $gclid;

        return $this;
    }

    /**
     * Get gclid
     *
     * @return string
     */
    public function getGclid()
    {
        return $this->gclid;
    }
}


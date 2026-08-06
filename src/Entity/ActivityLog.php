<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * ActivityLog - Nhật ký hoạt động hệ thống
 */
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(name: 'idx_activity_log_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_activity_log_action', columns: ['action'])]
#[ORM\Index(name: 'idx_activity_log_entity', columns: ['entityType'])]
#[ORM\Index(name: 'idx_activity_log_created', columns: ['createdAt'])]
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    // Action constants
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_TOGGLE = 'toggle';
    const ACTION_SETTINGS = 'settings';
    const ACTION_LOGIN = 'login';

    // Entity type constants
    const ENTITY_NEWS = 'news';
    const ENTITY_PAGE = 'page';
    const ENTITY_USER = 'user';
    const ENTITY_CATEGORY = 'category';
    const ENTITY_COMMENT = 'comment';
    const ENTITY_BANNER = 'banner';
    const ENTITY_BANNER_CATEGORY = 'banner_category';
    const ENTITY_MENU = 'menu';
    const ENTITY_MENU_ITEM = 'menu_item';
    const ENTITY_TAG = 'tag';
    const ENTITY_SETTINGS = 'settings';

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    /**
     * @var User
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private $user;

    #[ORM\Column(name: 'username', type: Types::STRING, length: 255, nullable: true)]
    private $username;

    #[ORM\Column(name: 'action', type: Types::STRING, length: 20)]
    private $action;

    #[ORM\Column(name: 'entityType', type: Types::STRING, length: 50)]
    private $entityType;

    #[ORM\Column(name: 'entityId', type: Types::INTEGER, nullable: true)]
    private $entityId;

    #[ORM\Column(name: 'entityTitle', type: Types::STRING, length: 255, nullable: true)]
    private $entityTitle;

    #[ORM\Column(name: 'details', type: Types::TEXT, nullable: true)]
    private $details;

    #[ORM\Column(name: 'ipAddress', type: Types::STRING, length: 45, nullable: true)]
    private $ipAddress;

    #[ORM\Column(name: 'userAgent', type: Types::STRING, length: 255, nullable: true)]
    private $userAgent;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    // ----- Getters & Setters -----

    public function getId()
    {
        return $this->id;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user = null)
    {
        $this->user = $user;
        return $this;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType()
    {
        return $this->entityType;
    }

    public function setEntityType($entityType)
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId()
    {
        return $this->entityId;
    }

    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getEntityTitle()
    {
        return $this->entityTitle;
    }

    public function setEntityTitle($entityTitle)
    {
        $this->entityTitle = $entityTitle;
        return $this;
    }

    public function getDetails()
    {
        return $this->details;
    }

    public function setDetails($details)
    {
        $this->details = $details;
        return $this;
    }

    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    public function setIpAddress($ipAddress)
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // ----- Helper Methods -----

    /**
     * Get human-readable action label in Vietnamese
     */
    public function getActionLabel()
    {
        $labels = [
            self::ACTION_CREATE => 'Tạo mới',
            self::ACTION_UPDATE => 'Cập nhật',
            self::ACTION_DELETE => 'Xóa',
            self::ACTION_TOGGLE => 'Thay đổi trạng thái',
            self::ACTION_SETTINGS => 'Cập nhật cài đặt',
            self::ACTION_LOGIN => 'Đăng nhập',
        ];

        return isset($labels[$this->action]) ? $labels[$this->action] : $this->action;
    }

    /**
     * Get human-readable entity type label in Vietnamese
     */
    public function getEntityTypeLabel()
    {
        $labels = [
            self::ENTITY_NEWS => 'Bài viết',
            self::ENTITY_PAGE => 'Trang',
            self::ENTITY_USER => 'Người dùng',
            self::ENTITY_CATEGORY => 'Danh mục',
            self::ENTITY_COMMENT => 'Bình luận',
            self::ENTITY_BANNER => 'Banner',
            self::ENTITY_BANNER_CATEGORY => 'Nhóm banner',
            self::ENTITY_MENU => 'Menu',
            self::ENTITY_MENU_ITEM => 'Menu item',
            self::ENTITY_TAG => 'Tag',
            self::ENTITY_SETTINGS => 'Cài đặt',
        ];

        return isset($labels[$this->entityType]) ? $labels[$this->entityType] : $this->entityType;
    }

    /**
     * Get CSS class for action badge
     */
    public function getActionBadgeClass()
    {
        $classes = [
            self::ACTION_CREATE => 'badge-success',
            self::ACTION_UPDATE => 'badge-warning',
            self::ACTION_DELETE => 'badge-danger',
            self::ACTION_TOGGLE => 'badge-info',
            self::ACTION_SETTINGS => 'badge-primary',
            self::ACTION_LOGIN => 'badge-dark',
        ];

        return isset($classes[$this->action]) ? $classes[$this->action] : 'badge-secondary';
    }

    /**
     * Get Font Awesome icon class for action
     */
    public function getActionIcon()
    {
        $icons = [
            self::ACTION_CREATE => 'fa-plus-circle',
            self::ACTION_UPDATE => 'fa-pencil',
            self::ACTION_DELETE => 'fa-trash',
            self::ACTION_TOGGLE => 'fa-toggle-on',
            self::ACTION_SETTINGS => 'fa-cog',
            self::ACTION_LOGIN => 'fa-sign-in',
        ];

        return isset($icons[$this->action]) ? $icons[$this->action] : 'fa-circle';
    }
}

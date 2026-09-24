<?php

namespace App\Entity\Contract;

/**
 * Entity hiển thị thành một hàng kéo-thả được trong admin: có thứ tự, bật/tắt
 * và một cái tên để ghi vào nhật ký hoạt động.
 *
 * Nhờ hợp đồng này mà App\Service\Admin\RowReorderService xử lý được cho mọi
 * màn hình quản lý thay vì mỗi controller chép lại một bản.
 */
interface SortableRow
{
    public function getId(): ?int;

    public function getPosition(): int;

    public function setPosition(int $position): self;

    public function getEnable(): bool;

    public function setEnable(bool $enable): self;

    /** Tên hiển thị trong thông báo và nhật ký hoạt động. */
    public function getName(): ?string;
}

<?php

namespace App\Enum;

/**
 * Quy trình xuất bản cho News (bài viết + trang, dùng chung entity qua
 * postType) — thay cho cờ enable đơn giản trước đây. Theo mẫu đã dùng ở
 * dự án minhduy: Bản nháp -> Chờ duyệt -> Đặt lịch -> Đã xuất bản -> Lưu trữ.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::PendingReview => 'Chờ duyệt',
            self::Scheduled => 'Đặt lịch',
            self::Published => 'Đã xuất bản',
            self::Archived => 'Lưu trữ',
        };
    }

    /**
     * Badge Bootstrap dùng ở danh sách admin.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge-secondary',
            self::PendingReview => 'badge-warning',
            self::Scheduled => 'badge-info',
            self::Published => 'badge-success',
            self::Archived => 'badge-dark',
        };
    }

    /**
     * @return array<string, string> label => value, dùng cho ChoiceType.
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}

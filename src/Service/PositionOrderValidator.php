<?php

namespace App\Service;

/**
 * Kiểm tra payload kéo-thả sắp xếp trước khi ghi cột `position`.
 *
 * Bản dùng chung, nhận thêm tên loại dữ liệu để thông báo lỗi đọc tự nhiên.
 * (App\Service\TestimonialOrderValidator là bản riêng cho Đánh giá khách
 * hàng, có cùng logic — nên gộp về đây khi có dịp chạm vào phần đó.)
 */
final class PositionOrderValidator
{
    /**
     * @param array<int> $current thứ tự đang lưu trong DB
     */
    public function validate(mixed $items, mixed $expected, array $current, string $noun): void
    {
        foreach ([$items, $expected] as $ids) {
            if (!is_array($ids) || !array_is_list($ids)) {
                throw new \InvalidArgumentException('Danh sách thứ tự không hợp lệ.');
            }

            foreach ($ids as $id) {
                if (!is_int($id) || $id < 1) {
                    throw new \InvalidArgumentException(sprintf('ID %s không hợp lệ.', $noun));
                }
            }

            if (count(array_unique($ids)) !== count($ids)) {
                throw new \InvalidArgumentException(sprintf('Danh sách có %s trùng lặp.', $noun));
            }
        }

        if ($expected !== $current) {
            throw new \DomainException('Danh sách đã thay đổi. Vui lòng tải lại trang trước khi sắp xếp.');
        }

        $complete = $current;
        sort($items);
        sort($complete);

        if ($items !== $complete) {
            throw new \InvalidArgumentException(sprintf('Phải sắp xếp toàn bộ %s trong một lần.', $noun));
        }
    }
}

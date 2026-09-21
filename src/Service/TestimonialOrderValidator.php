<?php

namespace App\Service;

final class TestimonialOrderValidator
{
    public function validate(mixed $items, mixed $expected, array $current): void
    {
        foreach ([$items, $expected] as $ids) {
            if (!is_array($ids) || !array_is_list($ids)) {
                throw new \InvalidArgumentException('Danh sách thứ tự không hợp lệ.');
            }
            foreach ($ids as $id) {
                if (!is_int($id) || $id < 1) {
                    throw new \InvalidArgumentException('ID đánh giá không hợp lệ.');
                }
            }
            if (count(array_unique($ids)) !== count($ids)) {
                throw new \InvalidArgumentException('Danh sách có đánh giá trùng lặp.');
            }
        }
        if ($expected !== $current) {
            throw new \DomainException('Danh sách đã thay đổi. Vui lòng tải lại trang trước khi sắp xếp.');
        }
        $complete = $current;
        sort($items);
        sort($complete);
        if ($items !== $complete) {
            throw new \InvalidArgumentException('Chỉ được sắp xếp toàn bộ đánh giá, không dùng danh sách đang lọc.');
        }
    }
}

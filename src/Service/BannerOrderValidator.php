<?php

namespace App\Service;

final class BannerOrderValidator
{
    public function validate(mixed $items, mixed $expected, array $current): void
    {
        foreach ([$items, $expected] as $ids) {
            if (!is_array($ids) || !array_is_list($ids) || count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
                throw new \InvalidArgumentException('Danh sách thứ tự không hợp lệ.');
            }
            foreach ($ids as $id) {
                if (!is_int($id) || $id < 1) {
                    throw new \InvalidArgumentException('ID banner không hợp lệ.');
                }
            }
        }
        if ($expected !== $current) {
            throw new \LogicException('Danh sách đã thay đổi. Vui lòng tải lại trang trước khi sắp xếp.');
        }
        $submitted = $items;
        $complete = $current;
        sort($submitted);
        sort($complete);
        if ($submitted !== $complete) {
            throw new \InvalidArgumentException('Cần gửi đầy đủ banner của vị trí hiển thị, không gửi danh sách đang lọc.');
        }
    }
}

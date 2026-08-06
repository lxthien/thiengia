<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;

class Builder
{
    public function mainMenu(FactoryInterface $factory, array $options)
    {
        $menu = $factory->createItem('root', array(
            'childrenAttributes' => array(
                'class' => 'nav navbar-nav',
            ),
        ));

        $menu->addChild('<span class="hidden">Trang chủ</span><i class="fa fa-home"></i>', [
            'route' => 'homepage',
            'extras' => ['safe_label' => true]
        ])
            ->setLinkAttribute('class', 'home')
            ->setLinkAttribute('aria-label', 'Xây Dựng Kim Anh');

        $menu->addChild('Giới thiệu', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'gioi-thieu']
        ])
            ->setAttribute('class', 'dropdown')
            ->setLinkAttribute('class', 'dropdown-toggle')
            ->setLinkAttribute('data-toggle', 'dropdown')
            ->setChildrenAttribute('class', 'dropdown-menu');

        $menu['Giới thiệu']->addChild('Về chúng tôi', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'gioi-thieu']
        ]);

        $menu['Giới thiệu']->addChild('Hồ sơ năng lực', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'ho-so-nang-luc']
        ]);

        $menu->addChild('Mẫu nhà đẹp', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'mau-nha-dep']
        ])
            ->setAttribute('class', 'dropdown')
            ->setLinkAttribute('class', 'dropdown-toggle')
            ->setLinkAttribute('data-toggle', 'dropdown')
            ->setChildrenAttribute('class', 'dropdown-menu');

        $menu['Mẫu nhà đẹp']->addChild('Mẫu nhà phố', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'mau-nha-dep', 'level2' => 'mau-nha-pho']
        ]);

        /*
        $menu['Mẫu nhà đẹp']->addChild('Mẫu nhà biệt thự', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'mau-nha-dep', 'level2' => 'mau-nha-biet-thu']
        ]);

        $menu['Mẫu nhà đẹp']->addChild('Mẫu nhà cấp 4', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'mau-nha-dep', 'level2' => 'mau-nha-cap-4']
        ]);
        */

        $menu->addChild('Dịch vụ', [
            'uri' => '#'
        ])
            ->setAttribute('class', 'dropdown')
            ->setLinkAttribute('class', 'dropdown-toggle')
            ->setLinkAttribute('data-toggle', 'dropdown')
            ->setChildrenAttribute('class', 'dropdown-menu');

        $menu['Dịch vụ']->addChild('Xây nhà trọn gói', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'bao-gia-xay-nha-tron-goi']
        ]);

        $menu['Dịch vụ']->addChild('Xây nhà phần thô', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'bao-gia-xay-nha-phan-tho']
        ]);

        $menu['Dịch vụ']->addChild('Thiết kế kiến trúc', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'bao-gia-thiet-ke-kien-truc']
        ]);

        $menu->addChild('Công trình', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'cong-trinh']
        ])
            ->setAttribute('class', 'dropdown')
            ->setLinkAttribute('class', 'dropdown-toggle')
            ->setLinkAttribute('data-toggle', 'dropdown')
            ->setChildrenAttribute('class', 'dropdown-menu');

        $menu['Công trình']->addChild('Xây dựng nhà', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'cong-trinh', 'level2' => 'xay-dung-nha']
        ]);

        $menu['Công trình']->addChild('Sửa chữa nhà', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'cong-trinh', 'level2' => 'sua-chua-nha']
        ]);

        $menu->addChild('Blog', [
            'route' => 'dynamic_post_page',
            'routeParameters' => ['slug' => 'blog']
        ])
            ->setAttribute('class', 'dropdown')
            ->setLinkAttribute('class', 'dropdown-toggle')
            ->setLinkAttribute('data-toggle', 'dropdown')
            ->setChildrenAttribute('class', 'dropdown-menu');

        $menu['Blog']->addChild('Kiến thức xây nhà', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'blog', 'level2' => 'kien-thuc-xay-nha']
        ]);

        $menu['Blog']->addChild('Kiến thức sửa nhà', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'blog', 'level2' => 'kien-thuc-sua-nha']
        ]);

        $menu['Blog']->addChild('Pháp lý xây dựng', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'blog', 'level2' => 'phap-ly-xay-dung']
        ]);

        $menu['Blog']->addChild('Phong thủy nhà ở', [
            'route' => 'dynamic_category_post',
            'routeParameters' => ['level1' => 'blog', 'level2' => 'phong-thuy']
        ]);

        /*
        $menu->addChild('Giới thiệu', [
            'route' => 'news_show',
            'routeParameters' => ['slug' => 'gioi-thieu']
        ])
        ->setAttribute('class', 'dropdown')
        ->setLinkAttribute('class', 'dropdown-toggle')
        ->setLinkAttribute('data-toggle', 'dropdown')
        ->setChildrenAttribute('class', 'dropdown-menu');

        $menu['Giới thiệu']->addChild('Về chúng tôi', [
            'route' => 'news_show',
            'routeParameters' => ['slug' => 'gioi-thieu']
        ]);

        $menu['Giới thiệu']->addChild('Tuyển dụng', [
            'route' => 'news_show',
            'routeParameters' => ['slug' => 'tuyen-dung-kien-truc-su-xay-dung']
        ]);

        $menu->addChild('Xây nhà', [
            'route' => 'news_category',
            'routeParameters' => ['level1' => 'xay-dung-nha-pho']
        ]);

        $menu->addChild('Sửa chữa nhà', [
            'route' => 'news_category',
            'routeParameters' => ['level1' => 'sua-chua-nha-dep']
        ]);

        $menu->addChild('Thiết kế', [
            'route' => 'news_category',
            'routeParameters' => ['level1' => 'thiet-ke-nha-pho']
        ]);

        $menu->addChild('Báo giá', [
            'route' => 'news_category',
            'routeParameters' => ['level1' => 'bang-gia']
        ]);

        $menu->addChild('Dự án', [
            'route' => 'news_category',
            'routeParameters' => ['level1' => 'du-an']
        ])
        ->setAttribute('class', 'dropdown')
        ->setLinkAttribute('class', 'dropdown-toggle')
        ->setLinkAttribute('data-toggle', 'dropdown')
        ->setChildrenAttribute('class', 'dropdown-menu');

        $menu['Dự án']->addChild('Xây dựng nhà phố', [
            'route' => 'list_category',
            'routeParameters' => ['level1' => 'du-an', 'level2' => 'du-an-xay-dung-nha-pho']
        ]);

        $menu['Dự án']->addChild('Sửa chữa nhà', [
            'route' => 'list_category',
            'routeParameters' => ['level1' => 'du-an', 'level2' => 'du-an-sua-chua-nha']
        ]);

        $menu->addChild('Tư vấn', [
            'route' => 'news_category',
            'routeParameters' => ['level1' => 'tu-van']
        ]);

        $menu->addChild('Ý kiến khách hàng', [
            'route' => 'news_category',
            'routeParameters' => ['level1' => 'y-kien-khach-hang']
        ]);
        */

        $menu->addChild('Liên hệ', [
            'route' => 'contact'
        ]);

        return $menu;
    }

    public function footerMenu(FactoryInterface $factory, array $options)
    {
        $footerMenu = $factory->createItem('root');

        return $footerMenu;
    }
}

<?php

namespace App\Service;

use App\Entity\Menu;
use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MenuService
{
    private $em;
    private $requestStack;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
    }

    /**
     * Get a menu by name
     *
     * @param string $menuName
     * @return Menu|null
     */
    public function getMenuByName($menuName)
    {
        return $this->em->getRepository(Menu::class)->findOneBy([
            'name' => $menuName,
            'enable' => true
        ]);
    }

    /**
     * Get all enabled root items for a menu
     *
     * @param Menu $menu
     * @return array
     */
    public function getMenuItems(Menu $menu)
    {
        return $this->em->getRepository(MenuItem::class)->findEnabledByMenu($menu);
    }

    /**
     * Get enabled children for a parent menu item
     *
     * @param MenuItem $parent
     * @return array
     */
    public function getChildItems(MenuItem $parent)
    {
        if (!$parent->getEnable()) {
            return [];
        }

        $children = $parent->getChildren()->filter(function(MenuItem $item) {
            return $item->getEnable();
        });

        return $children->toArray();
    }

    /**
     * Resolve the actual URL for a menu item (for different types)
     *
     * @param MenuItem $item
     * @return string|null
     */
    public function getItemUrl(MenuItem $item)
    {
        switch ($item->getType()) {
            case MenuItem::TYPE_URL:
                return $item->getUrl();
            
            case MenuItem::TYPE_NEWS:
                if ($item->getTargetId()) {
                    $news = $this->em->getRepository(\App\Entity\News::class)->find($item->getTargetId());
                    if ($news) {
                        return $this->formatUrl($news->getUrl());
                    }
                }
                break;
            
            case MenuItem::TYPE_CATEGORY:
                if ($item->getTargetId()) {
                    $category = $this->em->getRepository(\App\Entity\NewsCategory::class)->find($item->getTargetId());
                    if ($category) {
                        $url = $category->getUrl();
                        $parent = $category->getParentcat();
                        if ($parent && !is_string($parent) && $parent->getId()) {
                            $url = $parent->getUrl() . '/' . $url;
                        }
                        return $this->formatUrl($url);
                    }
                }
                break;
            
            case MenuItem::TYPE_PAGE:
                if ($item->getTargetId()) {
                    $page = $this->em->getRepository(\App\Entity\News::class)->findOneBy([
                        'id' => $item->getTargetId(),
                        'postType' => 'page',
                    ]);
                    if ($page) {
                        return $this->formatUrl($page->getUrl());
                    }
                }
                break;
        }

        return $item->getUrl();
    }

    /**
     * Build a hierarchical menu structure with resolved URLs
     *
     * @param Menu $menu
     * @return array
     */
    public function buildMenuStructure(Menu $menu)
    {
        $items = $this->getMenuItems($menu);
        $structure = [];

        foreach ($items as $item) {
            $structure[] = $this->buildItemStructure($item);
        }

        return $structure;
    }

    /**
     * Recursively build item structure
     *
     * @param MenuItem $item
     * @return array
     */
    private function buildItemStructure(MenuItem $item)
    {
        $data = [
            'id' => $item->getId(),
            'title' => $item->getTitle(),
            'url' => $this->getItemUrl($item),
            'class' => $item->getCssClass(),
            'target' => $item->getTargetAttr(),
            'title_attr' => $item->getTitleAttr(),
            'children' => [],
        ];

        $data['active'] = $this->isCurrentUrl($data['url']);

        $hasActiveChild = false;
        $children = $this->getChildItems($item);
        foreach ($children as $child) {
            $childData = $this->buildItemStructure($child);
            $data['children'][] = $childData;
            if ($childData['active'] || $childData['has_active_child']) {
                $hasActiveChild = true;
            }
        }
        $data['has_active_child'] = $hasActiveChild;

        return $data;
    }

    /**
     * Render menu HTML with SEO best practices
     *
     * @param Menu $menu
     * @param array $options
     * @return string
     */
    public function renderMenu(Menu $menu, array $options = [])
    {
        $structure = $this->buildMenuStructure($menu);
        return $this->renderMenuItemsList($structure, $options);
    }

    /**
     * Render a list of menu items recursively
     *
     * @param array $items
     * @param array $options
     * @param int $depth
     * @return string
     */
    private function renderMenuItemsList(array $items, array $options = [], $depth = 0)
    {
        if (empty($items)) {
            return '';
        }

        $ulClass = $options['ul_class'] ?? 'nav navbar-nav';
        $liClass = $options['li_class'] ?? 'nav-item';
        $aClass = $options['a_class'] ?? 'nav-link';
        $activeClass = $options['active_class'] ?? 'active';

        // Use different classes for nested lists
        if ($depth > 0) {
            $ulClass = $options['sub_ul_class'] ?? 'dropdown-menu';
            $liClass = $options['sub_li_class'] ?? 'dropdown-item';
        }

        $html = '<ul class="' . htmlspecialchars($ulClass) . '">';

        foreach ($items as $item) {
            $html .= $this->renderMenuItem($item, $options, $depth, $activeClass, $liClass, $aClass);
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render a single menu item with SEO attributes
     *
     * @param array $item
     * @param array $options
     * @param int $depth
     * @param string $activeClass
     * @param string $liClass
     * @param string $aClass
     * @return string
     */
    private function renderMenuItem(array $item, array $options, $depth, $activeClass, $liClass, $aClass)
    {
        $html = '<li class="' . htmlspecialchars($liClass);

        // Add custom CSS class if provided
        if (!empty($item['class'])) {
            $html .= ' ' . htmlspecialchars($item['class']);
        }

        // Add active class if URL matches
        if ($this->isCurrentUrl($item['url'])) {
            $html .= ' ' . htmlspecialchars($activeClass);
        }

        // Add dropdown class if has children
        if (!empty($item['children'])) {
            $html .= ' dropdown';
        }

        $html .= '">';

        // Allow Home icon for Trang chủ, and match live site structure
        $label = htmlspecialchars($item['title']);
        if ($item['title'] === 'Trang chủ' || $item['url'] === '/' || $item['url'] === '') {
            if ($depth === 0) {
                 $label = '<span class="hidden">' . htmlspecialchars($item['title']) . '</span><i class="fa fa-home"></i>';
                 $aClass .= ' home';
            }
        }

        // Build link attributes with SEO
        $linkAttrs = [
            'class' => htmlspecialchars($aClass),
            'href' => $this->buildUrl($item['url']),
        ];

        if (!empty($item['target'])) {
            $linkAttrs['target'] = htmlspecialchars($item['target']);
        }

        // Add title attribute for SEO (tooltip)
        if (!empty($item['title_attr'])) {
            $linkAttrs['title'] = htmlspecialchars($item['title_attr']);
        }

        // Add data attributes for accessibility
        if (!empty($item['children'])) {
            $linkAttrs['data-toggle'] = 'dropdown';
            $linkAttrs['aria-haspopup'] = 'true';
            $linkAttrs['aria-expanded'] = 'false';
        }

        $html .= '<a';
        foreach ($linkAttrs as $attr => $value) {
            $html .= ' ' . $attr . '="' . $value . '"';
        }
        $html .= '>';

        $html .= $label;

        // Add dropdown indicator if has children
        if (!empty($item['children'])) {
            $html .= ' <span class="caret"></span>';
        }

        $html .= '</a>';

        // Recursively render children
        if (!empty($item['children'])) {
            $html .= $this->renderMenuItemsList($item['children'], $options, $depth + 1);
        }

        $html .= '</li>';

        return $html;
    }

    /**
     * Build URL with proper handling of relative/absolute URLs
     *
     * @param string $url
     * @return string
     */
    private function buildUrl($url)
    {
        return $url;
    }

    /**
     * Format URL to ensure leading and trailing slashes while avoiding doubles
     * 
     * @param string $url
     * @return string
     */
    private function formatUrl($url)
    {
        if (empty($url) || $url === '#') {
            return '#';
        }

        $url = trim($url, '/');
        return '/' . $url . '/';
    }

    /**
     * Check if the given URL is the current page
     *
     * @param string $url
     * @return bool
     */
    private function isCurrentUrl($url)
    {
        if (empty($url) || $url === '#' || preg_match('#^https?://#i', $url)) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        $currentPath = rtrim($request->getPathInfo(), '/') . '/';
        $itemPath = rtrim($url, '/') . '/';

        return $currentPath === $itemPath;
    }
}

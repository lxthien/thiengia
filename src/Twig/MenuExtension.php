<?php

namespace App\Twig;

use App\Service\MenuService;
use App\Entity\Menu;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    private $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('render_menu', [$this, 'renderMenu'], ['is_safe' => ['html']]),
            new TwigFunction('get_menu', [$this, 'getMenu']),
            new TwigFunction('menu_items', [$this, 'getMenuItems']),
        ];
    }

    /**
     * Get the resolved, hierarchical item structure for a menu by name
     *
     * @param string $menuName
     * @return array
     */
    public function getMenuItems($menuName)
    {
        $menu = $this->menuService->getMenuByName($menuName);

        if (!$menu) {
            return [];
        }

        return $this->menuService->buildMenuStructure($menu);
    }

    /**
     * Render a menu by name
     *
     * @param string $menuName
     * @param array $options
     * @return string
     */
    public function renderMenu($menuName, array $options = [])
    {
        $menu = $this->menuService->getMenuByName($menuName);
        
        if (!$menu) {
            return '';
        }

        return $this->menuService->renderMenu($menu, $options);
    }

    /**
     * Get a menu object by name
     *
     * @param string $menuName
     * @return Menu|null
     */
    public function getMenu($menuName)
    {
        return $this->menuService->getMenuByName($menuName);
    }

}

<?php

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Entity\News;
use App\Entity\NewsCategory;
use App\Form\MenuType;
use App\Form\MenuItemType;
use App\Entity\ActivityLog;
use App\Service\ActivityLogService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller used to manage menus in the backend.
 * @Route("/admin/menu")
 * @Security("has_role('ROLE_ADMIN')")
 */
class MenuController extends Controller
{
    /**
     * Lists all menu entities.
     *
     * @Route("/", name="admin_menu_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $menus = $em->getRepository(Menu::class)->findAll();

        return $this->render('admin/menu/index.html.twig', [
            'objects' => $menus,
        ]);
    }

    /**
     * Creates a new menu entity.
     *
     * @Route("/new", name="admin_menu_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($menu);
            $em->flush();

            $this->addFlash('success', 'action.created_successfully');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
        }

        return $this->render('admin/menu/new.html.twig', [
            'object' => $menu,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edits a menu entity and its items.
     *
     * @Route("/{id}/edit", name="admin_menu_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Menu $menu)
    {
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
        }

        $em = $this->getDoctrine()->getManager();

        // Get available content for drag-drop
        $posts = $em->getRepository(News::class)->findBy(['postType' => 'post'], ['title' => 'ASC']);
        $pages = $em->getRepository(News::class)->findBy(['postType' => 'page'], ['title' => 'ASC']);
        $categories = $em->getRepository(\App\Entity\NewsCategory::class)->findAll();
        $usedTargetIds = $this->getUsedTargetIds($menu);

        return $this->render('admin/menu/edit.html.twig', [
            'object' => $menu,
            'form' => $form->createView(),
            'available_posts' => $posts,
            'available_pages' => $pages,
            'available_categories' => $categories,
            'used_post_ids' => $usedTargetIds[MenuItem::TYPE_NEWS],
            'used_page_ids' => $usedTargetIds[MenuItem::TYPE_PAGE],
            'used_category_ids' => $usedTargetIds[MenuItem::TYPE_CATEGORY],
        ]);
    }

    /**
     * Deletes a menu entity.
     *
     * @Route("/{id}/delete", name="admin_menu_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request, Menu $menu)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_menu_index');
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($menu);
        $em->flush();

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_menu_index');
    }

    /**
     * Adds a menu item.
     *
     * @Route("/{id}/item/new", name="admin_menu_item_new")
     * @Method({"GET", "POST"})
     */
    public function addItemAction(Request $request, Menu $menu)
    {
        $menuItem = new MenuItem();
        $menuItem->setMenu($menu);

        $form = $this->createForm(MenuItemType::class, $menuItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculate next position
            $em = $this->getDoctrine()->getManager();
            $maxPosition = $em->createQuery(
                'SELECT MAX(mi.position) FROM App\Entity\MenuItem mi WHERE mi.menu = :menu AND mi.parent IS NULL'
            )->setParameter('menu', $menu)->getSingleScalarResult();

            $menuItem->setPosition(($maxPosition ?? 0) + 1);

            $em->persist($menuItem);
            $em->flush();

            $this->addFlash('success', 'action.created_successfully');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
        }

        return $this->render('admin/menu/item_form.html.twig', [
            'menu' => $menu,
            'object' => $menuItem,
            'form' => $form->createView(),
            'parent' => null,
        ]);
    }

    /**
     * Edits a menu item.
     *
     * @Route("/item/{id}/edit", name="admin_menu_item_edit")
     * @Method({"GET", "POST"})
     */
    public function editItemAction(Request $request, MenuItem $menuItem)
    {
        $form = $this->createForm(MenuItemType::class, $menuItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menuItem->getMenu()->getId()]);
        }

        return $this->render('admin/menu/item_form.html.twig', [
            'menu' => $menuItem->getMenu(),
            'object' => $menuItem,
            'form' => $form->createView(),
            'parent' => $menuItem->getParent(),
        ]);
    }

    /**
     * Deletes a menu item.
     *
     * @Route("/item/{id}/delete", name="admin_menu_item_delete")
     * @Method("POST")
     */
    public function deleteItemAction(Request $request, MenuItem $menuItem)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_menu_edit', ['id' => $menuItem->getMenu()->getId()]);
        }

        $menu = $menuItem->getMenu();
        $em = $this->getDoctrine()->getManager();
        $em->remove($menuItem);
        $em->flush();

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
    }

    /**
     * Reorders menu items via AJAX drag-drop.
     *
     * @Route("/{id}/reorder", name="admin_menu_reorder")
     * @Method("POST")
     */
    public function reorderAction(Request $request, Menu $menu)
    {
        $data = json_decode($request->getContent(), true);
        $em = $this->getDoctrine()->getManager();

        if (!isset($data['items']) || !is_array($data['items'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data format'], 400);
        }

        try {
            $this->reorderMenuItems($data['items'], $menu, null, $em);
            $em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Menu reordered successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get a single menu item data for inline editing
     *
     * @Route("/item/{id}/get", name="admin_menu_item_get")
     * @Method("GET")
     */
    public function getItemAction(MenuItem $menuItem)
    {
        return new JsonResponse([
            'success' => true,
            'item' => [
                'id' => $menuItem->getId(),
                'title' => $menuItem->getTitle(),
                'url' => $menuItem->getUrl(),
                'type' => $menuItem->getType(),
                'cssClass' => $menuItem->getCssClass(),
                'targetAttr' => $menuItem->getTargetAttr(),
                'titleAttr' => $menuItem->getTitleAttr(),
                'enable' => $menuItem->getEnable(),
            ]
        ]);
    }

    /**
     * Update a menu item inline via AJAX
     *
     * @Route("/item/{id}/update", name="admin_menu_item_update")
     * @Method("POST")
     */
    public function updateItemAction(Request $request, MenuItem $menuItem)
    {
        $data = json_decode($request->getContent(), true);

        try {
            if (isset($data['title']) && !empty(trim($data['title']))) {
                $menuItem->setTitle(trim($data['title']));
            }
            if (array_key_exists('url', $data)) {
                $menuItem->setUrl($this->formatUrl($data['url']));
            }
            if (isset($data['cssClass'])) {
                $menuItem->setCssClass($data['cssClass']);
            }
            if (isset($data['targetAttr'])) {
                $menuItem->setTargetAttr($data['targetAttr']);
            }
            if (isset($data['titleAttr'])) {
                $menuItem->setTitleAttr($data['titleAttr']);
            }
            if (isset($data['enable'])) {
                $menuItem->setEnable((bool)$data['enable']);
            }

            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Item updated successfully',
                'item' => [
                    'id' => $menuItem->getId(),
                    'title' => $menuItem->getTitle(),
                    'url' => $menuItem->getUrl(),
                    'enable' => $menuItem->getEnable(),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
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

        // If it's an external link, don't touch it
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }

        $url = trim($url, '/');
        return '/' . $url . '/';
    }

    /**
     * Recursively reorder menu items from drag-drop data.
     *
     * @param array $items
     * @param Menu $menu
     * @param MenuItem|null $parent
     * @param EntityManager $em
     * @param int $position
     */
    private function reorderMenuItems(array $items, Menu $menu, MenuItem $parent = null, $em, $position = 0)
    {
        foreach ($items as $item) {
            $menuItem = $em->getRepository(MenuItem::class)->find($item['id']);

            if (!$menuItem || $menuItem->getMenu()->getId() !== $menu->getId()) {
                continue;
            }

            $menuItem->setPosition($position);
            $menuItem->setParent($parent);
            $em->persist($menuItem);

            if (isset($item['children']) && is_array($item['children'])) {
                $this->reorderMenuItems($item['children'], $menu, $menuItem, $em, 0);
            }

            $position++;
        }
    }

    /**
     * Add content item to menu via AJAX
     *
     * @Route("/{menuId}/add-item", name="admin_menu_add_content_item")
     * @Method("POST")
     */
    public function addContentItemAction(Request $request, $menuId)
    {
        $data = json_decode($request->getContent(), true);
        $em = $this->getDoctrine()->getManager();

        $menu = $em->getRepository(Menu::class)->find($menuId);
        if (!$menu) {
            return new JsonResponse(['success' => false, 'message' => 'Menu not found'], 404);
        }

        try {
            $itemType = $data['type'] ?? null;
            $title = $data['title'] ?? null;
            $url = $data['url'] ?? null;
            $itemId = $data['item_id'] ?? null;

            if (!$itemType) {
                return new JsonResponse(['success' => false, 'message' => 'Missing type parameter'], 400);
            }

            if (in_array($itemType, [MenuItem::TYPE_NEWS, MenuItem::TYPE_PAGE, MenuItem::TYPE_CATEGORY], true) && $itemId) {
                $alreadyExists = $em->getRepository(MenuItem::class)->existsByMenuAndTarget($menu, $itemType, $itemId);
                if ($alreadyExists) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Mục này đã tồn tại trong menu'
                    ], 400);
                }
            }

            // Create new menu item
            $menuItem = new MenuItem();
            $menuItem->setMenu($menu);
            $menuItem->setType($itemType);
            $menuItem->setTargetAttr('_self');

            // Get the target item to set title and URL
            if ($itemType === MenuItem::TYPE_URL) {
                if (!$title || !$url) {
                    return new JsonResponse(['success' => false, 'message' => 'Title and URL are required'], 400);
                }
                $menuItem->setTitle($title);
                $menuItem->setUrl($url);
            } elseif ($itemType === MenuItem::TYPE_NEWS && $itemId) {
                $menuItem->setTargetId($itemId);
                $menuItem->setTargetType($itemType);
                $post = $em->getRepository(News::class)->findOneBy([
                    'id' => $itemId,
                    'postType' => 'post',
                ]);
                if ($post) {
                    $menuItem->setTitle($title ?: $post->getTitle());
                    $menuItem->setUrl($this->formatUrl($post->getUrl()));
                }
            } elseif ($itemType === MenuItem::TYPE_PAGE && $itemId) {
                $menuItem->setTargetId($itemId);
                $menuItem->setTargetType($itemType);
                $page = $em->getRepository(News::class)->findOneBy([
                    'id' => $itemId,
                    'postType' => 'page',
                ]);
                if ($page) {
                    $menuItem->setTitle($title ?: $page->getTitle());
                    $menuItem->setUrl($this->formatUrl($page->getUrl()));
                }
            } elseif ($itemType === MenuItem::TYPE_CATEGORY && $itemId) {
                $menuItem->setTargetId($itemId);
                $menuItem->setTargetType($itemType);
                $category = $em->getRepository(\App\Entity\NewsCategory::class)->find($itemId);
                if ($category) {
                    $menuItem->setTitle($title ?: $category->getName());
                    
                    $url = $category->getUrl();
                    $parent = $category->getParentcat();
                    if ($parent && !is_string($parent) && $parent->getId()) {
                        $url = $parent->getUrl() . '/' . $url;
                    }
                    $menuItem->setUrl($this->formatUrl($url));
                }
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Invalid item type or missing ID'], 400);
            }

            // Calculate next position
            $maxPosition = $em->createQuery(
                'SELECT MAX(mi.position) FROM App\Entity\MenuItem mi WHERE mi.menu = :menu AND mi.parent IS NULL'
            )->setParameter('menu', $menu)->getSingleScalarResult();

            $menuItem->setPosition(($maxPosition ?? 0) + 1);
            $menuItem->setEnable(true);

            $em->persist($menuItem);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Item added successfully',
                'item' => [
                    'id' => $menuItem->getId(),
                    'title' => $menuItem->getTitle(),
                    'url' => $menuItem->getUrl(),
                    'type' => $menuItem->getType(),
                    'enable' => $menuItem->getEnable(),
                    'targetAttr' => $menuItem->getTargetAttr(),
                    'cssClass' => $menuItem->getCssClass(),
                    'titleAttr' => $menuItem->getTitleAttr(),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get available content items for menu
     *
     * @Route("/{id}/available-items", name="admin_menu_available_items")
     * @Method("GET")
     */
    public function getAvailableItemsAction(Menu $menu)
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        if (!$request->isXmlHttpRequest()) {
            throw $this->createAccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();

        $posts = $em->getRepository(News::class)->findBy(['postType' => 'post'], ['title' => 'ASC']);
        $pages = $em->getRepository(News::class)->findBy(['postType' => 'page'], ['title' => 'ASC']);
        $categories = $em->getRepository(\App\Entity\NewsCategory::class)->findAll();

        $data = [
            'posts' => array_map(function($item) {
                return ['id' => $item->getId(), 'title' => $item->getTitle()];
            }, (array)$posts),
            'pages' => array_map(function($item) {
                return ['id' => $item->getId(), 'title' => $item->getTitle()];
            }, (array)$pages),
            'categories' => array_map(function($item) {
                return ['id' => $item->getId(), 'title' => $item->getName()];
            }, (array)$categories),
        ];

        return new JsonResponse($data);
    }

    private function getUsedTargetIds(Menu $menu)
    {
        $usedTargetIds = [
            MenuItem::TYPE_NEWS => [],
            MenuItem::TYPE_PAGE => [],
            MenuItem::TYPE_CATEGORY => [],
        ];

        foreach ($menu->getItems() as $item) {
            $targetType = $item->getTargetType();
            $targetId = $item->getTargetId();

            if (!$targetType || !$targetId || !array_key_exists($targetType, $usedTargetIds)) {
                continue;
            }

            $usedTargetIds[$targetType][] = (int) $targetId;
        }

        foreach ($usedTargetIds as $type => $ids) {
            $usedTargetIds[$type] = array_values(array_unique($ids));
        }

        return $usedTargetIds;
    }
}

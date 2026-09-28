<?php

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Entity\News;
use App\Entity\NewsCategory;
use App\Form\MenuType;
use App\Form\MenuItemType;
use App\Service\MenuService;
use App\Service\MenuUrlNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage menus in the backend.
 */
#[Route('/admin/menu')]
#[IsGranted('ROLE_ADMIN')]
class MenuController extends AbstractController
{
    /**
     * Tên menu mà giao diện website đang gọi (menu_items('Main Menu') trong
     * templates/v3/layout/_header.html.twig và _mobile_menu.html.twig).
     * Đổi tên / tắt / xoá các menu này làm menu trên website biến mất.
     */
    public const SITE_MENU_NAMES = ['Main Menu'];

    /**
     * Header và menu mobile của website chỉ hiển thị 2 cấp (mục gốc + mục con).
     */
    public const MAX_DEPTH = 2;

    private const CSRF_ID = 'menu_editor';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly MenuService $menuService,
    ) {
    }

    /**
     * Lists all menu entities.
     */
    #[Route('/', name: 'admin_menu_index', methods: ['GET'])]
    public function indexAction()
    {
        $menus = $this->em->getRepository(Menu::class)->findBy([], ['name' => 'ASC']);

        return $this->render('admin/menu/index.html.twig', [
            'objects' => $menus,
            'site_menu_names' => self::SITE_MENU_NAMES,
        ]);
    }

    /**
     * Creates a new menu entity.
     */
    #[Route('/new', name: 'admin_menu_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($menu);
            $this->em->flush();

            $this->addFlash('success', 'Đã tạo menu. Thêm các mục từ cột bên trái.');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
        }

        return $this->render('admin/menu/new.html.twig', [
            'object' => $menu,
            'form' => $form->createView(),
            'site_menu_names' => self::SITE_MENU_NAMES,
        ]);
    }

    /**
     * Edits a menu entity and its items.
     */
    #[Route('/{id}/edit', name: 'admin_menu_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Menu $menu)
    {
        $originalName = $menu->getName();
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Đã lưu cài đặt menu.');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
        }

        // Form lỗi: hiển thị lại với tên cũ ở tiêu đề trang (tên mới chưa được lưu).
        if ($form->isSubmitted()) {
            $menu->setName($originalName);
        }

        $posts = $this->em->getRepository(News::class)->findBy(['postType' => 'post'], ['title' => 'ASC']);
        $pages = $this->em->getRepository(News::class)->findBy(['postType' => 'page'], ['title' => 'ASC']);
        $categories = $this->em->getRepository(NewsCategory::class)->findBy([], ['name' => 'ASC']);
        $usedTargetIds = $this->getUsedTargetIds($menu);

        // URL thật website đang dùng cho từng mục (mục trỏ tới bài/trang/danh mục
        // luôn lấy URL hiện tại của nội dung đó, không phải URL lưu lúc thêm vào menu).
        $resolvedUrls = [];
        foreach ($menu->getItems() as $item) {
            $resolvedUrls[$item->getId()] = $this->menuService->getItemUrl($item);
        }

        return $this->render('admin/menu/edit.html.twig', [
            'object' => $menu,
            'original_name' => $originalName,
            'form' => $form->createView(),
            'available_posts' => $posts,
            'available_pages' => $pages,
            'available_categories' => $categories,
            'used_post_ids' => $usedTargetIds[MenuItem::TYPE_NEWS],
            'used_page_ids' => $usedTargetIds[MenuItem::TYPE_PAGE],
            'used_category_ids' => $usedTargetIds[MenuItem::TYPE_CATEGORY],
            'resolved_urls' => $resolvedUrls,
            'site_menu_names' => self::SITE_MENU_NAMES,
            'max_depth' => self::MAX_DEPTH,
            'csrf_id' => self::CSRF_ID,
        ]);
    }

    /**
     * Deletes a menu entity.
     */
    #[Route('/{id}/delete', name: 'admin_menu_delete', methods: ['POST'])]
    public function deleteAction(Request $request, Menu $menu)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            $this->addFlash('danger', 'Phiên làm việc đã hết hạn. Tải lại trang rồi thử lại.');

            return $this->redirectToRoute('admin_menu_index');
        }

        $name = $menu->getName();
        $this->em->remove($menu);
        $this->em->flush();

        $this->addFlash('success', sprintf('Đã xoá menu "%s".', $name));

        return $this->redirectToRoute('admin_menu_index');
    }

    /**
     * Adds a menu item (trang form cũ — màn sửa menu hiện thêm mục qua AJAX).
     */
    #[Route('/{id}/item/new', name: 'admin_menu_item_new', methods: ['GET', 'POST'])]
    public function addItemAction(Request $request, Menu $menu)
    {
        $menuItem = new MenuItem();
        $menuItem->setMenu($menu);

        $form = $this->createForm(MenuItemType::class, $menuItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $menuItem->setPosition($this->nextRootPosition($menu));

            $this->em->persist($menuItem);
            $this->em->flush();

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
     * Edits a menu item (trang form cũ — màn sửa menu hiện sửa mục qua hộp thoại).
     */
    #[Route('/item/{id}/edit', name: 'admin_menu_item_edit', methods: ['GET', 'POST'])]
    public function editItemAction(Request $request, MenuItem $menuItem)
    {
        $form = $this->createForm(MenuItemType::class, $menuItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
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
     * Deletes a menu item (cùng các mục con — cascade ở entity/DB).
     */
    #[Route('/item/{id}/delete', name: 'admin_menu_item_delete', methods: ['POST'])]
    public function deleteItemAction(Request $request, MenuItem $menuItem)
    {
        $menu = $menuItem->getMenu();

        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            $this->addFlash('danger', 'Phiên làm việc đã hết hạn. Tải lại trang rồi thử lại.');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
        }

        $title = $menuItem->getTitle();
        $childCount = $menuItem->getChildren()->count();
        $this->em->remove($menuItem);
        $this->em->flush();

        $this->addFlash('success', $childCount
            ? sprintf('Đã xoá "%s" và %d mục con.', $title, $childCount)
            : sprintf('Đã xoá "%s".', $title));

        return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->getId()]);
    }

    /**
     * Reorders menu items via AJAX drag-drop.
     */
    #[Route('/{id}/reorder', name: 'admin_menu_reorder', methods: ['POST'])]
    public function reorderAction(Request $request, Menu $menu)
    {
        if ($denied = $this->denyInvalidAjaxToken($request)) {
            return $denied;
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || !is_array($data['items'])) {
            return $this->jsonError('Dữ liệu sắp xếp không hợp lệ.');
        }

        if ($this->treeDepth($data['items']) > self::MAX_DEPTH) {
            return $this->jsonError(sprintf('Website chỉ hiển thị %d cấp menu. Hãy đưa mục cấp %d lên cấp trên.', self::MAX_DEPTH, self::MAX_DEPTH + 1));
        }

        try {
            $this->reorderMenuItems($data['items'], $menu, null);
            $this->em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Đã lưu thứ tự menu.']);
        } catch (\Throwable $e) {
            return $this->jsonError('Không thể lưu thứ tự menu: ' . $e->getMessage());
        }
    }

    /**
     * Get a single menu item data for inline editing
     */
    #[Route('/item/{id}/get', name: 'admin_menu_item_get', methods: ['GET'])]
    public function getItemAction(MenuItem $menuItem)
    {
        return new JsonResponse([
            'success' => true,
            'item' => $this->serializeItem($menuItem),
        ]);
    }

    /**
     * Update a menu item inline via AJAX
     */
    #[Route('/item/{id}/update', name: 'admin_menu_item_update', methods: ['POST'])]
    public function updateItemAction(Request $request, MenuItem $menuItem)
    {
        if ($denied = $this->denyInvalidAjaxToken($request)) {
            return $denied;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->jsonError('Dữ liệu không hợp lệ.');
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return $this->jsonError('Tiêu đề hiển thị không được để trống.');
        }
        if (mb_strlen($title) > 255) {
            return $this->jsonError('Tiêu đề hiển thị tối đa 255 ký tự.');
        }

        // Chỉ liên kết tùy chỉnh mới sửa URL: mục trỏ tới bài/trang/danh mục luôn
        // dùng URL hiện tại của nội dung (MenuService::getItemUrl).
        if ($menuItem->getType() === MenuItem::TYPE_URL && array_key_exists('url', $data)) {
            $url = MenuUrlNormalizer::normalize($data['url']);
            if ($url === null) {
                return $this->jsonError('URL không hợp lệ. Dùng https://…, mailto:, tel:, #điểm-neo hoặc đường dẫn bắt đầu bằng /.');
            }
            $menuItem->setUrl($url);
        }

        $menuItem->setTitle($title);
        $menuItem->setTargetAttr(($data['targetAttr'] ?? '_self') === '_blank' ? '_blank' : '_self');
        $menuItem->setCssClass($this->cleanCssClass($data['cssClass'] ?? ''));
        $menuItem->setTitleAttr(mb_substr(trim((string) ($data['titleAttr'] ?? '')), 0, 255) ?: null);
        $menuItem->setEnable((bool) ($data['enable'] ?? true));

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            return $this->jsonError('Không thể lưu mục: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Đã lưu mục menu.',
            'item' => $this->serializeItem($menuItem),
        ]);
    }

    /**
     * Add content item to menu via AJAX
     */
    #[Route('/{menuId}/add-item', name: 'admin_menu_add_content_item', methods: ['POST'])]
    public function addContentItemAction(Request $request, $menuId)
    {
        if ($denied = $this->denyInvalidAjaxToken($request)) {
            return $denied;
        }

        $menu = $this->em->getRepository(Menu::class)->find($menuId);
        if (!$menu) {
            return $this->jsonError('Không tìm thấy menu.', 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->jsonError('Dữ liệu không hợp lệ.');
        }

        $itemType = $data['type'] ?? null;
        $title = trim((string) ($data['title'] ?? ''));
        $itemId = isset($data['item_id']) ? (int) $data['item_id'] : null;

        $menuItem = new MenuItem();
        $menuItem->setMenu($menu);
        $menuItem->setType((string) $itemType);
        $menuItem->setTargetAttr('_self');

        if ($itemType === MenuItem::TYPE_URL) {
            $url = MenuUrlNormalizer::normalize($data['url'] ?? '');
            if ($title === '' || trim((string) ($data['url'] ?? '')) === '') {
                return $this->jsonError('Nhập cả URL và văn bản liên kết.');
            }
            if ($url === null) {
                return $this->jsonError('URL không hợp lệ. Dùng https://…, mailto:, tel:, #điểm-neo hoặc đường dẫn bắt đầu bằng /.');
            }
            $menuItem->setTitle(mb_substr($title, 0, 255));
            $menuItem->setUrl($url);
        } elseif (in_array($itemType, [MenuItem::TYPE_NEWS, MenuItem::TYPE_PAGE, MenuItem::TYPE_CATEGORY], true) && $itemId) {
            if ($this->em->getRepository(MenuItem::class)->existsByMenuAndTarget($menu, $itemType, $itemId)) {
                return $this->jsonError('Mục này đã có trong menu.');
            }

            $target = $this->findTarget($itemType, $itemId);
            if ($target === null) {
                return $this->jsonError('Nội dung này không còn tồn tại. Tải lại trang để cập nhật danh sách.', 404);
            }

            $menuItem->setTargetId($itemId);
            $menuItem->setTargetType($itemType);
            $menuItem->setTitle(mb_substr($title ?: $target['title'], 0, 255));
            $menuItem->setUrl($target['url']);
        } else {
            return $this->jsonError('Loại mục không hợp lệ.');
        }

        $menuItem->setPosition($this->nextRootPosition($menu));
        $menuItem->setEnable(true);

        try {
            $this->em->persist($menuItem);
            $this->em->flush();
        } catch (\Throwable $e) {
            return $this->jsonError('Không thể thêm mục: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Đã thêm vào menu.',
            'item' => $this->serializeItem($menuItem),
        ]);
    }

    /**
     * Get available content items for menu
     */
    #[Route('/{id}/available-items', name: 'admin_menu_available_items', methods: ['GET'])]
    public function getAvailableItemsAction(Menu $menu)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request->isXmlHttpRequest()) {
            throw $this->createAccessDeniedException();
        }

        $posts = $this->em->getRepository(News::class)->findBy(['postType' => 'post'], ['title' => 'ASC']);
        $pages = $this->em->getRepository(News::class)->findBy(['postType' => 'page'], ['title' => 'ASC']);
        $categories = $this->em->getRepository(NewsCategory::class)->findBy([], ['name' => 'ASC']);

        $data = [
            'posts' => array_map(fn ($item) => ['id' => $item->getId(), 'title' => $item->getTitle()], $posts),
            'pages' => array_map(fn ($item) => ['id' => $item->getId(), 'title' => $item->getTitle()], $pages),
            'categories' => array_map(fn ($item) => ['id' => $item->getId(), 'title' => $item->getName()], $categories),
        ];

        return new JsonResponse($data);
    }

    /**
     * Các API JSON của màn sửa menu gửi token qua header X-CSRF-Token (xem menu-editor.js).
     */
    private function denyInvalidAjaxToken(Request $request): ?JsonResponse
    {
        if ($this->isCsrfTokenValid(self::CSRF_ID, (string) $request->headers->get('X-CSRF-Token'))) {
            return null;
        }

        return $this->jsonError('Phiên làm việc đã hết hạn. Tải lại trang rồi thử lại.', 403);
    }

    private function jsonError(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => $message], $status);
    }

    private function serializeItem(MenuItem $menuItem): array
    {
        return [
            'id' => $menuItem->getId(),
            'title' => $menuItem->getTitle(),
            'url' => $menuItem->getUrl(),
            'resolvedUrl' => $this->menuService->getItemUrl($menuItem),
            'type' => $menuItem->getType(),
            'cssClass' => $menuItem->getCssClass(),
            'targetAttr' => $menuItem->getTargetAttr(),
            'titleAttr' => $menuItem->getTitleAttr(),
            'enable' => $menuItem->getEnable(),
        ];
    }

    /**
     * @return array{title: string, url: string}|null
     */
    private function findTarget(string $type, int $id): ?array
    {
        if ($type === MenuItem::TYPE_CATEGORY) {
            $category = $this->em->getRepository(NewsCategory::class)->find($id);
            if (!$category) {
                return null;
            }

            $url = $category->getUrl();
            $parent = $category->getParentcat();
            if ($parent instanceof NewsCategory && $parent->getId()) {
                $url = $parent->getUrl() . '/' . $url;
            }

            return ['title' => (string) $category->getName(), 'url' => $this->formatContentUrl($url)];
        }

        $news = $this->em->getRepository(News::class)->findOneBy([
            'id' => $id,
            'postType' => $type === MenuItem::TYPE_PAGE ? 'page' : 'post',
        ]);

        return $news ? ['title' => (string) $news->getTitle(), 'url' => $this->formatContentUrl($news->getUrl())] : null;
    }

    /**
     * Slug nội dung ("a/b") → đường dẫn site ("/a/b/").
     */
    private function formatContentUrl(?string $url): string
    {
        $url = trim((string) $url, '/');

        return $url === '' ? '#' : '/' . $url . '/';
    }

    private function cleanCssClass(mixed $value): ?string
    {
        // Chỉ ký tự hợp lệ của tên class, tách bằng khoảng trắng.
        $classes = preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $classes = array_filter($classes, fn ($class) => preg_match('/^[A-Za-z_-][\w-]*$/', $class));

        return $classes ? mb_substr(implode(' ', $classes), 0, 255) : null;
    }

    private function nextRootPosition(Menu $menu): int
    {
        $maxPosition = $this->em->createQuery(
            'SELECT MAX(mi.position) FROM App\Entity\MenuItem mi WHERE mi.menu = :menu AND mi.parent IS NULL'
        )->setParameter('menu', $menu)->getSingleScalarResult();

        return ($maxPosition ?? 0) + 1;
    }

    private function treeDepth(array $items, int $depth = 1): int
    {
        $max = $items ? $depth : $depth - 1;
        foreach ($items as $item) {
            if (!empty($item['children']) && is_array($item['children'])) {
                $max = max($max, $this->treeDepth($item['children'], $depth + 1));
            }
        }

        return $max;
    }

    /**
     * Recursively reorder menu items from drag-drop data.
     */
    private function reorderMenuItems(array $items, Menu $menu, ?MenuItem $parent, int $position = 0): void
    {
        foreach ($items as $item) {
            $menuItem = $this->em->getRepository(MenuItem::class)->find($item['id'] ?? 0);

            if (!$menuItem || $menuItem->getMenu()->getId() !== $menu->getId()) {
                continue;
            }

            $menuItem->setPosition($position);
            $menuItem->setParent($parent);

            if (isset($item['children']) && is_array($item['children'])) {
                $this->reorderMenuItems($item['children'], $menu, $menuItem, 0);
            }

            $position++;
        }
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

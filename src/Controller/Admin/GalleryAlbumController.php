<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\GalleryAlbum;
use App\Entity\GalleryImage;
use App\Form\GalleryAlbumType;
use App\Service\ActivityLogService;
use App\Service\GalleryAlbumOrderValidator;
use App\Service\GalleryImageOrderValidator;
use App\Service\GalleryImageInput;
use Doctrine\DBAL\LockMode;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage gallery albums (hình ảnh hoạt động) in the backend.
 */
#[Route('/admin/gallery-album')]
#[IsGranted('ROLE_EDITOR')]
class GalleryAlbumController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
        private readonly GalleryAlbumOrderValidator $orderValidator,
        private readonly LoggerInterface $logger,
        private readonly GalleryImageOrderValidator $imageOrderValidator,
        private readonly GalleryImageInput $imageInput,
    ) {
    }

    /**
     * Lists all gallery album entities.
     */
    #[Route('/', name: 'admin_gallery_album_index', methods: ['GET'])]
    public function indexAction(Request $request): Response
    {
        $all = $this->em->getRepository(GalleryAlbum::class)->findAllOrdered();
        $query = mb_substr(trim((string) $request->query->get('q', '')), 0, 150);
        $status = (string) $request->query->get('status', '');
        if (!in_array($status, ['', 'visible', 'hidden', 'empty'], true)) $status = '';
        $albums = array_values(array_filter($all, static function (GalleryAlbum $album) use ($query, $status): bool {
            if ($status === 'visible' && !$album->getEnable()) return false;
            if ($status === 'hidden' && $album->getEnable()) return false;
            if ($status === 'empty' && !$album->getImages()->isEmpty()) return false;
            return $query === '' || mb_stripos((string) $album->getName(), $query) !== false;
        }));
        return $this->render('admin/gallery_album/index.html.twig', [
            'objects' => $albums, 'total_count' => count($all), 'query' => $query, 'status_filter' => $status,
            'reorder_enabled' => $query === '' && $status === '',
        ]);
    }

    /**
     * Creates a new gallery album entity (chưa quản lý ảnh — cần album tồn tại trước).
     */
    #[Route('/new', name: 'admin_gallery_album_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $album = new GalleryAlbum();

        $panel = $request->query->getBoolean('_panel');
        $form = $this->createForm(GalleryAlbumType::class, $album, [
            'action' => $this->generateUrl('admin_gallery_album_new', $panel ? ['_panel' => 1] : []),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $maxPosition = $this->em->createQuery(
                'SELECT MAX(a.position) FROM App\Entity\GalleryAlbum a'
            )->getSingleScalarResult();
            $album->setPosition(($maxPosition ?? -1) + 1);

            $this->em->persist($album);
            $this->em->flush();

            $this->activityLogService->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_GALLERY_ALBUM,
                $album->getId(),
                $album->getName()
            );

            if ($panel) return $this->json([
                'success' => true, 'message' => 'Đã tạo album. Hãy thêm ảnh từ thư viện.',
                'redirect' => $this->generateUrl('admin_gallery_album_edit', ['id' => $album->getId()]),
            ]);
            $this->addFlash('success', 'action.created_successfully');

            return $this->redirectToRoute('admin_gallery_album_edit', ['id' => $album->getId()]);
        }

        return $this->render($panel ? 'admin/gallery_album/_panel_form.html.twig' : 'admin/gallery_album/new.html.twig', [
            'object' => $album, 'form' => $form->createView(), 'panel_title' => 'Thêm album',
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    /**
     * Edits a gallery album's info + manages its images.
     */
    #[Route('/{id}/edit', name: 'admin_gallery_album_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, GalleryAlbum $album)
    {
        $panel = $request->query->getBoolean('_panel');
        $editor = $request->query->getBoolean('_editor') && $request->isXmlHttpRequest();
        $form = $this->createForm(GalleryAlbumType::class, $album, [
            'action' => $this->generateUrl('admin_gallery_album_edit', ['id' => $album->getId()] + ($panel ? ['_panel' => 1] : [])),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $diffDetails = $this->activityLogService->getEntityDiff($album);

            $this->em->flush();

            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_GALLERY_ALBUM,
                $album->getId(),
                $album->getName(),
                $diffDetails
            );

            if ($panel || $editor) return $this->json(['success' => true, 'message' => 'Đã lưu thông tin album.', 'name' => $album->getName()]);
            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_gallery_album_edit', ['id' => $album->getId()]);
        }

        return $this->render($panel ? 'admin/gallery_album/_panel_form.html.twig' : ($editor ? 'admin/gallery_album/_info_form.html.twig' : 'admin/gallery_album/edit.html.twig'), [
            'object' => $album, 'form' => $form->createView(), 'panel_title' => 'Chỉnh sửa album',
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    /**
     * Deletes a gallery album entity (cascade xóa ảnh con).
     */
    #[Route('/{id}/delete', name: 'admin_gallery_album_delete', methods: ['POST'])]
    public function deleteAction(Request $request, GalleryAlbum $album)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            if ($request->isXmlHttpRequest()) return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
            $this->addFlash('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
            return $this->redirectToRoute('admin_gallery_album_index');
        }

        $albumName = $album->getName();
        $albumId = $album->getId();

        $this->em->remove($album);
        $this->em->flush();

        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_GALLERY_ALBUM,
            $albumId,
            $albumName
        );

        if ($request->isXmlHttpRequest()) return $this->json(['success' => true, 'message' => 'Đã xóa album. File ảnh trong thư viện vẫn được giữ lại.']);
        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_gallery_album_index');
    }


    #[Route('/{id}/image/add', name: 'admin_gallery_album_image_add', methods: ['POST'])]
    public function addImageAction(Request $request, GalleryAlbum $album): JsonResponse
    {
        $data = $this->imageRequest($request, $album);
        if ($data instanceof JsonResponse) return $data;
        return $this->mutateImages($album, function () use ($album, $data): array {
            $url = $this->imageInput->imageUrl($data['imageUrl'] ?? null, $this->getParameter('kernel.project_dir') . '/public');
            $meta = $this->imageInput->metadata($data);
            if ($this->em->getRepository(GalleryImage::class)->findOneBy(['album' => $album, 'imageUrl' => $url])) {
                throw new \DomainException('Ảnh đã có trong album. Vui lòng chọn ảnh khác.');
            }
            $max = $this->em->createQuery('SELECT MAX(i.position) FROM App\\Entity\\GalleryImage i WHERE i.album = :album')
                ->setParameter('album', $album)->getSingleScalarResult();
            $image = (new GalleryImage())->setAlbum($album)->setImageUrl($url)->setPosition(($max ?? -1) + 1)
                ->setCaption($meta['caption'] ?: null)->setAlt($meta['alt'] ?: null);
            $this->em->persist($image);
            return ['message' => 'Đã thêm ảnh vào album.', 'entity' => $image, 'new' => true];
        });
    }

    #[Route('/image/{id}/update', name: 'admin_gallery_album_image_update', methods: ['POST'])]
    public function updateImageAction(Request $request, GalleryImage $image): JsonResponse
    {
        $album = $image->getAlbum();
        $data = $this->imageRequest($request, $album);
        if ($data instanceof JsonResponse) return $data;
        return $this->mutateImages($album, function () use ($image, $data): array {
            $meta = $this->imageInput->metadata($data);
            $this->em->refresh($image, LockMode::PESSIMISTIC_WRITE);
            $expected = $data['expected'] ?? null;
            if (!is_array($expected) || !is_string($expected['caption'] ?? null) || !is_string($expected['alt'] ?? null)) {
                throw new \InvalidArgumentException('Thiếu phiên bản chú thích hiện tại. Vui lòng tải lại trang.');
            }
            if ($expected['caption'] !== (string) $image->getCaption() || $expected['alt'] !== (string) $image->getAlt()) {
                throw new \DomainException('Ảnh đã được chỉnh sửa ở nơi khác. Hãy lưu lại nội dung đang nhập và tải lại trang.');
            }
            $image->setCaption($meta['caption'] ?: null)->setAlt($meta['alt'] ?: null);
            return ['message' => 'Đã lưu chú thích và alt.', 'entity' => $image];
        });
    }

    #[Route('/image/{id}/delete', name: 'admin_gallery_album_image_delete', methods: ['POST'])]
    public function deleteImageAction(Request $request, GalleryImage $image): JsonResponse
    {
        $album = $image->getAlbum();
        $data = $this->imageRequest($request, $album);
        if ($data instanceof JsonResponse) return $data;
        return $this->mutateImages($album, function () use ($image): array {
            $this->em->remove($image);
            return ['message' => 'Đã gỡ ảnh khỏi album. File ảnh trong thư viện vẫn được giữ lại.'];
        });
    }

    #[Route('/{id}/images/reorder', name: 'admin_gallery_album_images_reorder', methods: ['POST'])]
    public function reorderImagesAction(Request $request, GalleryAlbum $album): JsonResponse
    {
        $data = $this->imageRequest($request, $album);
        if ($data instanceof JsonResponse) return $data;
        return $this->mutateImages($album, function () use ($album, $data): array {
            $images = $this->em->getRepository(GalleryImage::class)->findBy(['album' => $album], ['position' => 'ASC', 'id' => 'ASC']);
            $current = array_map(static fn (GalleryImage $image): int => $image->getId(), $images);
            $this->imageOrderValidator->validate($data['items'] ?? null, $data['expected'] ?? null, $current);
            $positions = array_flip($data['items']);
            foreach ($images as $image) $image->setPosition($positions[$image->getId()]);
            return ['message' => 'Đã lưu thứ tự ảnh. Ảnh đầu tiên là ảnh đại diện.'];
        });
    }

    private function imageRequest(Request $request, GalleryAlbum $album): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) return $this->json(['success' => false, 'message' => 'Dữ liệu yêu cầu không hợp lệ.'], 400);
        if (!is_string($data['token'] ?? null) || !$this->isCsrfTokenValid('gallery_images_' . $album->getId(), $data['token'])) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn hoặc album không khớp. Vui lòng tải lại trang.'], 403);
        }
        return $data;
    }

    private function mutateImages(GalleryAlbum $album, callable $operation): JsonResponse
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            // Serialize additions, removals and reordering within this album.
            $this->em->lock($album, LockMode::PESSIMISTIC_WRITE);
            $result = $operation();
            $this->em->flush();
            if (isset($result['entity'])) {
                $image = $result['entity'];
                $result['image'] = $this->serializeImage($image);
                if (!empty($result['new'])) {
                    $result['html'] = $this->renderView('admin/gallery_album/_image_row.html.twig', ['image' => $image]);
                }
                unset($result['entity'], $result['new']);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) $connection->rollBack();
            if ($exception instanceof \InvalidArgumentException) return $this->json(['success' => false, 'message' => $exception->getMessage()], 422);
            if ($exception instanceof \DomainException) return $this->json(['success' => false, 'message' => $exception->getMessage()], 409);
            $this->logger->error('Gallery image mutation failed.', ['exception' => $exception]);
            return $this->json(['success' => false, 'message' => 'Không lưu được thay đổi. Vui lòng tải lại trang và thử lại.'], 500);
        }
        $this->activityLogService->log(ActivityLog::ACTION_UPDATE, ActivityLog::ENTITY_GALLERY_ALBUM, $album->getId(), $album->getName(), $result['message']);
        return $this->json(['success' => true] + $result);
    }

    #[Route('/{id}/visibility', name: 'admin_gallery_album_visibility', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function visibilityAction(Request $request, GalleryAlbum $album): JsonResponse
    {
        if (!$this->isCsrfTokenValid('gallery_album_visibility_' . $album->getId(), $request->request->get('token'))) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $enable = $request->request->get('enable');
        if (!in_array($enable, ['0', '1'], true)) return $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ.'], 400);
        $album->setEnable($enable === '1');
        $this->em->flush();
        $this->activityLogService->log(ActivityLog::ACTION_TOGGLE, ActivityLog::ENTITY_GALLERY_ALBUM, $album->getId(), $album->getName(), 'enable: ' . $enable);
        return $this->json(['success' => true, 'message' => $album->getEnable() ? 'Đã bật hiển thị album.' : 'Đã ẩn album.']);
    }

    #[Route('/reorder', name: 'admin_gallery_album_reorder', methods: ['POST'])]
    public function reorderAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['token'] ?? null) || !$this->isCsrfTokenValid('reorder_gallery_albums', $data['token'])) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $albums = $this->em->getRepository(GalleryAlbum::class)->findForReorder();
            $current = array_map(static fn (GalleryAlbum $album): int => $album->getId(), $albums);
            $this->orderValidator->validate($data['items'] ?? null, $data['expected'] ?? null, $current);
            $positions = array_flip($data['items']);
            foreach ($albums as $album) $album->setPosition($positions[$album->getId()]);
            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) $connection->rollBack();
            if ($exception instanceof \LogicException) {
                return $this->json(['success' => false, 'message' => $exception->getMessage()], $exception instanceof \InvalidArgumentException ? 400 : 409);
            }
            $this->logger->error('Gallery album reordering failed.', ['exception' => $exception]);
            return $this->json(['success' => false, 'message' => 'Không lưu được thứ tự. Vui lòng tải lại trang và thử lại.'], 500);
        }
        $this->activityLogService->log(ActivityLog::ACTION_UPDATE, ActivityLog::ENTITY_GALLERY_ALBUM, null, 'Thứ tự album', json_encode($data['items']));
        return $this->json(['success' => true, 'message' => 'Đã lưu thứ tự album.']);
    }

    private function serializeImage(GalleryImage $image): array
    {
        $parts = explode('/', $image->getImageUrl());
        $filename = array_pop($parts);
        $thumb = implode('/', $parts) . '/thumbs/' . $filename;

        return [
            'id' => $image->getId(),
            'imageUrl' => $image->getImageUrl(),
            'thumb' => $thumb,
            'caption' => $image->getCaption(),
            'alt' => $image->getAlt(),
            'position' => $image->getPosition(),
        ];
    }
}

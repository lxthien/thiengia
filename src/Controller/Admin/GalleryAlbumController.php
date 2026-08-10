<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\GalleryAlbum;
use App\Entity\GalleryImage;
use App\Form\GalleryAlbumType;
use App\Service\ActivityLogService;

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
    ) {
    }

    /**
     * Lists all gallery album entities.
     */
    #[Route('/', name: 'admin_gallery_album_index', methods: ['GET'])]
    public function indexAction()
    {
        $albums = $this->em->getRepository(GalleryAlbum::class)->findAllOrdered();

        return $this->render('admin/gallery_album/index.html.twig', ['objects' => $albums]);
    }

    /**
     * Creates a new gallery album entity (chưa quản lý ảnh — cần album tồn tại trước).
     */
    #[Route('/new', name: 'admin_gallery_album_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $album = new GalleryAlbum();

        $form = $this->createForm(GalleryAlbumType::class, $album);
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

            $this->addFlash('success', 'action.created_successfully');

            return $this->redirectToRoute('admin_gallery_album_edit', ['id' => $album->getId()]);
        }

        return $this->render('admin/gallery_album/new.html.twig', [
            'object' => $album,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edits a gallery album's info + manages its images.
     */
    #[Route('/{id}/edit', name: 'admin_gallery_album_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, GalleryAlbum $album)
    {
        $form = $this->createForm(GalleryAlbumType::class, $album);
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

            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_gallery_album_edit', ['id' => $album->getId()]);
        }

        return $this->render('admin/gallery_album/edit.html.twig', [
            'object' => $album,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a gallery album entity (cascade xóa ảnh con).
     */
    #[Route('/{id}/delete', name: 'admin_gallery_album_delete', methods: ['POST'])]
    public function deleteAction(Request $request, GalleryAlbum $album)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
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

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_gallery_album_index');
    }

    /**
     * Adds an image to the album (chọn từ Media Library). AJAX.
     */
    #[Route('/{id}/image/add', name: 'admin_gallery_album_image_add', methods: ['POST'])]
    public function addImageAction(Request $request, GalleryAlbum $album)
    {
        $data = json_decode($request->getContent(), true);
        $imageUrl = trim((string) ($data['imageUrl'] ?? ''));

        if ($imageUrl === '') {
            return new JsonResponse(['success' => false, 'message' => 'Thiếu ảnh.'], 400);
        }

        $maxPosition = $this->em->createQuery(
            'SELECT MAX(i.position) FROM App\Entity\GalleryImage i WHERE i.album = :album'
        )->setParameter('album', $album)->getSingleScalarResult();

        $image = new GalleryImage();
        $image->setAlbum($album);
        $image->setImageUrl($imageUrl);
        $image->setCaption(trim((string) ($data['caption'] ?? '')) ?: null);
        $image->setAlt(trim((string) ($data['alt'] ?? '')) ?: null);
        $image->setPosition(($maxPosition ?? -1) + 1);

        $this->em->persist($image);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'image' => $this->serializeImage($image),
        ]);
    }

    /**
     * Updates an image's caption/alt. AJAX.
     */
    #[Route('/image/{id}/update', name: 'admin_gallery_album_image_update', methods: ['POST'])]
    public function updateImageAction(Request $request, GalleryImage $image)
    {
        $data = json_decode($request->getContent(), true);

        if (array_key_exists('caption', $data)) {
            $image->setCaption(trim((string) $data['caption']) ?: null);
        }
        if (array_key_exists('alt', $data)) {
            $image->setAlt(trim((string) $data['alt']) ?: null);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'image' => $this->serializeImage($image)]);
    }

    /**
     * Removes an image from the album (không xóa file vật lý trong Media Library). AJAX.
     */
    #[Route('/image/{id}/delete', name: 'admin_gallery_album_image_delete', methods: ['POST'])]
    public function deleteImageAction(Request $request, GalleryImage $image)
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!$this->isCsrfTokenValid('delete', $data['token'] ?? null)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 400);
        }

        $this->em->remove($image);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Reorders images within an album via AJAX drag-drop.
     */
    #[Route('/{id}/images/reorder', name: 'admin_gallery_album_images_reorder', methods: ['POST'])]
    public function reorderImagesAction(Request $request, GalleryAlbum $album)
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || !is_array($data['items'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data format'], 400);
        }

        try {
            foreach ($data['items'] as $position => $id) {
                $image = $this->em->getRepository(GalleryImage::class)->find($id);
                if (!$image || $image->getAlbum()->getId() !== $album->getId()) {
                    continue;
                }
                $image->setPosition($position);
            }

            $this->em->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
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

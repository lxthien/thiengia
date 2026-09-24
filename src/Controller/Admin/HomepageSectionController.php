<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\HomepageSection;
use App\Form\HomepageSectionType;
use App\Repository\HomepageSectionRepository;
use App\Service\ActivityLogService;
use App\Service\Admin\RowReorderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Quản lý các khối trang chủ: sắp thứ tự, bật/tắt và sửa phần chữ.
 *
 * Không có thêm/xóa — tập khối là cố định do dev định nghĩa trong
 * App\Enum\SectionType, khối chưa có trong DB thì tạo bằng
 * `php bin/console app:homepage:seed-sections`.
 */
#[Route('/admin/homepage-section')]
#[IsGranted('ROLE_EDITOR')]
class HomepageSectionController extends AbstractController
{
    private const NOUN = 'khối trang chủ';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HomepageSectionRepository $repository,
        private readonly ActivityLogService $activityLogService,
        private readonly RowReorderService $rowReorder,
    ) {
    }

    #[Route('/', name: 'admin_homepage_section_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return $this->render('admin/homepage_section/index.html.twig', [
            'objects' => $this->repository->findAllOrdered(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_homepage_section_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editAction(Request $request, HomepageSection $section): Response
    {
        $form = $this->createForm(HomepageSectionType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $details = $this->activityLogService->getEntityDiff($section);
            $this->em->flush();
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_HOMEPAGE_SECTION,
                $section->getId(),
                $section->getName(),
                $details,
            );

            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_homepage_section_index');
        }

        return $this->render('admin/homepage_section/edit.html.twig', [
            'object' => $section,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/visibility', name: 'admin_homepage_section_visibility', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function visibilityAction(Request $request, HomepageSection $section): JsonResponse
    {
        return $this->rowReorder->toggleVisibility(
            $request,
            $section,
            'homepage_section_visibility_',
            ActivityLog::ENTITY_HOMEPAGE_SECTION,
            'Đã bật khối "%s" trên trang chủ.',
            'Đã ẩn khối "%s" khỏi trang chủ.',
        );
    }

    #[Route('/reorder', name: 'admin_homepage_section_reorder', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reorderAction(Request $request): JsonResponse
    {
        return $this->rowReorder->reorder(
            $request,
            'reorder_homepage_sections',
            fn (): array => $this->repository->findForReorder(),
            self::NOUN,
            ActivityLog::ENTITY_HOMEPAGE_SECTION,
            'Thứ tự khối trang chủ',
        );
    }
}

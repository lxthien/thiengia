<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
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
 * Quản lý công trình thực tế — khối "Công trình" trên trang chủ đọc từ đây.
 */
#[Route('/admin/project')]
#[IsGranted('ROLE_EDITOR')]
class ProjectController extends AbstractController
{
    private const NOUN = 'công trình';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProjectRepository $repository,
        private readonly ActivityLogService $activityLogService,
        private readonly RowReorderService $rowReorder,
    ) {
    }

    #[Route('/', name: 'admin_project_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        $objects = $this->repository->findAllOrdered();
        $publicRoot = realpath($this->getParameter('kernel.project_dir') . '/public');
        $availableCovers = [];
        foreach ($objects as $project) {
            $cover = $project->getCoverImage();
            $resolved = $cover ? realpath($publicRoot . '/' . ltrim($cover, '/')) : false;
            $availableCovers[$project->getId()] = $resolved && str_starts_with($resolved, $publicRoot . DIRECTORY_SEPARATOR) && is_file($resolved);
        }
        return $this->render('admin/project/index.html.twig', [
            'objects' => $objects,
            'availableCovers' => $availableCovers,
        ]);
    }

    #[Route('/new', name: 'admin_project_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request): Response
    {
        return $this->editForm($request, new Project(), true);
    }

    #[Route('/{id}/edit', name: 'admin_project_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editAction(Request $request, Project $project): Response
    {
        return $this->editForm($request, $project, false);
    }

    private function editForm(Request $request, Project $project, bool $new): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($new) {
                $project->setPosition($this->rowReorder->nextPosition(Project::class));
            }

            $details = $new ? null : $this->activityLogService->getEntityDiff($project);
            $this->em->persist($project);
            $this->em->flush();
            $this->activityLogService->log(
                $new ? ActivityLog::ACTION_CREATE : ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_PROJECT,
                $project->getId(),
                $project->getName(),
                $details,
            );

            $this->addFlash('success', $new ? 'action.created_successfully' : 'action.updated_successfully');

            return $this->redirectToRoute('admin_project_index');
        }

        return $this->render($new ? 'admin/project/new.html.twig' : 'admin/project/edit.html.twig', [
            'object' => $project,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_project_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAction(Request $request, Project $project): Response
    {
        if (!$this->isCsrfTokenValid('delete_project_' . $project->getId(), $request->request->get('token'))) {
            $this->addFlash('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');

            return $this->redirectToRoute('admin_project_index');
        }

        $id = $project->getId();
        $name = $project->getName();
        $this->em->remove($project);
        $this->em->flush();
        $this->activityLogService->log(ActivityLog::ACTION_DELETE, ActivityLog::ENTITY_PROJECT, $id, $name);
        $this->addFlash('success', 'Đã xóa công trình. Ảnh trong thư viện vẫn được giữ lại.');

        return $this->redirectToRoute('admin_project_index');
    }

    #[Route('/{id}/visibility', name: 'admin_project_visibility', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function visibilityAction(Request $request, Project $project): JsonResponse
    {
        return $this->rowReorder->toggleVisibility(
            $request,
            $project,
            'project_visibility_',
            ActivityLog::ENTITY_PROJECT,
            'Đã bật hiển thị công trình "%s".',
            'Đã ẩn công trình "%s".',
        );
    }

    #[Route('/reorder', name: 'admin_project_reorder', methods: ['POST'])]
    public function reorderAction(Request $request): JsonResponse
    {
        return $this->rowReorder->reorder(
            $request,
            'reorder_projects',
            fn (): array => $this->repository->findForReorder(),
            self::NOUN,
            ActivityLog::ENTITY_PROJECT,
            'Thứ tự công trình',
        );
    }
}

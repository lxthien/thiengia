<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\NewsCategory;
use App\Form\NewsCategoryType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage post category contents in the backend.
 */
#[Route('/admin/newscategory')]
#[IsGranted('ROLE_EDITOR')]
class NewsCategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Lists all NewsCategory entities.
     */
    #[Route('/', name: 'admin_newscategory_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $repository = $this->em->getRepository(NewsCategory::class);
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => (string) $request->query->get('status', ''),
        ];
        if (!in_array($filters['status'], ['', 'enabled', 'disabled'], true)) {
            $filters['status'] = '';
        }
        $filtering = $filters['q'] !== '' || $filters['status'] !== '';
        $levels = [];

        if ($filtering) {
            // Kết quả lọc hiển thị dạng phẳng, kèm cấp và danh mục cha để định vị trong cây.
            $categories = $repository->search($filters['q'], $filters['status']);
            foreach ($categories as $category) {
                $levels[$category->getId()] = $this->getCategoryLevel($category);
            }
        } else {
            $categories = $repository->findBy(['parentcat' => null], ['name' => 'ASC']);
        }

        return $this->render('admin/newscategory/index.html.twig', [
            'objects' => $categories,
            'levels' => $levels,
            'filters' => $filters,
            'filtering' => $filtering,
            'news_counts' => $repository->countNewsByCategory(),
            'total' => $repository->count([]),
        ]);
    }

    private function getCategoryLevel(NewsCategory $category): int
    {
        $level = 0;
        $visited = [$category->getId() => true];
        $parent = $category->getParentcat();

        while (null !== $parent && !isset($visited[$parent->getId()])) {
            $visited[$parent->getId()] = true;
            ++$level;
            $parent = $parent->getParentcat();
        }

        return $level;
    }

    /**
     * Creates a new NewsCategory entity.
     */
    #[Route('/new', name: 'admin_newscategory_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request, Slugger $slugger)
    {
        $category = new NewsCategory();
        $category->setAuthor($this->getUser());

        // See https://symfony.com/doc/current/book/forms.html#submitting-forms-with-multiple-buttons
        $form = $this->createForm(NewsCategoryType::class, $category)
            ->add('saveAndCreateNew', SubmitType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->em->persist($category);
            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_CATEGORY,
                $category->getId(),
                $category->getName()
            );

            $this->addFlash('success', 'action.created_successfully');

            if ($form->get('saveAndCreateNew')->isClicked()) {
                return $this->redirectToRoute('admin_newscategory_new');
            }

            return $this->redirectToRoute('admin_newscategory_index');
        }

        return $this->render('admin/newscategory/new.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing NewsCategory entity.
     */
    #[Route('/{id}/edit', requirements: ['id' => '\d+'], name: 'admin_newscategory_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, NewsCategory $category, Slugger $slugger)
    {
        $form = $this->createForm(NewsCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture changes before flush
            $diffDetails = $this->activityLogService->getEntityDiff($category);

            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_CATEGORY,
                $category->getId(),
                $category->getName(),
                $diffDetails
            );

            $this->addFlash('success', 'action.updated_successfully');
            return $this->redirectToRoute('admin_newscategory_index');
        }

        return $this->render('admin/newscategory/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a NewsCategory entity.
     */
    #[Route('/{id}/delete', name: 'admin_newscategory_delete', methods: ['POST'])]
    public function deleteAction(Request $request, NewsCategory $category)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_newscategory_index');
        }

        // parentcat_id không có ON DELETE — xóa danh mục cha sẽ vi phạm khóa ngoại.
        if ($category->getChildren()->count() > 0) {
            $this->addFlash('warning', 'Không thể xóa "'.$category->getName().'" vì còn danh mục con. Hãy chuyển danh mục con sang danh mục khác trước.');

            return $this->redirectToRoute('admin_newscategory_index');
        }

        $catName = $category->getName();
        $catId = $category->getId();

        $this->em->remove($category);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_CATEGORY,
            $catId,
            $catName
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_newscategory_index');
    }
}

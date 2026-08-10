<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\News;
use App\Form\PageType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage page contents in the backend.
 */
#[Route('/admin/page')]
#[IsGranted('ROLE_EDITOR')]
class PageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Lists all News entities.
     */
    #[Route('/', name: 'admin_page_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $repository = $this->em->getRepository(News::class);
        $searchQuery = trim((string) $request->query->get('q', ''));

        if ($searchQuery !== '') {
            $pages = $repository->searchPages($searchQuery);
            $pageLevels = [];

            foreach ($pages as $page) {
                $pageLevels[$page->getId()] = $this->getPageLevel($page);
            }
        } else {
            $pages = $repository->findPagesAsTree();
            $pageLevels = [];
        }

        return $this->render('admin/page/index.html.twig', [
            'pages' => $pages,
            'page_levels' => $pageLevels,
            'search_query' => $searchQuery,
        ]);
    }

    /**
     * Creates a new News entity.
     */
    #[Route('/new', name: 'admin_page_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request, Slugger $slugger)
    {
        $news = new News();
        $news->setAuthor($this->getUser());
        $news->setPostType('page');

        $form = $this->createForm(PageType::class, $news)
            ->add('save', SubmitType::class)
            ->add('saveAndCreateNew', SubmitType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle Media Picker selection (bypasses Vich to avoid path conflicts)
                $this->applyMediaPickerUrl($request, $news);

                $unitOfWork = $this->em->getUnitOfWork();
                $originalData = $unitOfWork->getOriginalEntityData($news);

                // Update createdAt if enable changed from false to true
                if (isset($originalData['enable']) && !$originalData['enable'] && $news->getEnable()) {
                    $news->setCreatedAt(new \DateTime());
                }

                $this->em->persist($news);
                $this->em->flush();

                // Activity Log
                $this->activityLogService->log(
                    ActivityLog::ACTION_CREATE,
                    ActivityLog::ENTITY_PAGE,
                    $news->getId(),
                    $news->getTitle()
                );

                $this->addFlash('success', 'action.created_successfully');

                if ($form->get('saveAndCreateNew')->isClicked()) {
                    return $this->redirectToRoute('admin_page_new');
                }

                return $this->redirectToRoute('admin_page_edit', array(
                    'id' => $news->getId()
                ));
            } catch (\DBALException $e) {
                $message = sprintf('DBALException [%i]: %s', $e->getCode(), $e->getMessage());
            } catch (\PDOException $e) {
                $message = sprintf('PDOException [%i]: %s', $e->getCode(), $e->getMessage());
            } catch (\ORMException $e) {
                $message = sprintf('ORMException [%i]: %s', $e->getCode(), $e->getMessage());
            } catch (\Exception $e) {
                $message = sprintf('Exception [%i]: %s', $e->getCode(), $e->getMessage());
            }

            $this->addFlash('error', $message);
        }

        return $this->render('admin/page/new.html.twig', [
            'object' => $news,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing News entity.
     */
    #[Route('/{id}/edit', requirements: ['id' => '\d+'], name: 'admin_page_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, News $news, Slugger $slugger)
    {
        $form = $this->createForm(PageType::class, $news)
            ->add('save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle Media Picker selection (bypasses Vich to avoid path conflicts)
                $this->applyMediaPickerUrl($request, $news);

                $unitOfWork = $this->em->getUnitOfWork();
                $originalData = $unitOfWork->getOriginalEntityData($news);

                // Update createdAt if enable changed from false to true
                if (isset($originalData['enable']) && !$originalData['enable'] && $news->getEnable()) {
                    $news->setCreatedAt(new \DateTime());
                }

                // Handle postType change logic
                $originalPostType = isset($originalData['postType']) ? $originalData['postType'] : 'page';
                $newPostType = $news->getPostType();

                // From page to post
                if ($originalPostType === 'page' && $newPostType === 'post') {
                    // Remove parent relationship
                    $news->setParent(null);
                }
                // From post to page
                elseif ($originalPostType === 'post' && $newPostType === 'page') {
                    // Remove relationships with categories and tags
                    $news->getCategory()->clear();
                    $news->getTags()->clear();
                    // Ensure parent is null for pages
                    $news->setParent(null);
                }

                // Capture changes before flush
                $diffDetails = $this->activityLogService->getEntityDiff($news);

                $this->em->flush();

                // Activity Log
                $this->activityLogService->log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::ENTITY_PAGE,
                    $news->getId(),
                    $news->getTitle(),
                    $diffDetails
                );

                $this->addFlash('success', 'action.updated_successfully');

                // If postType changed to post, redirect to news edit
                if ($originalPostType === 'page' && $newPostType === 'post') {
                    return $this->redirectToRoute('admin_news_edit', array(
                        'id' => $news->getId()
                    ));
                }

                return $this->redirectToRoute('admin_page_edit', array(
                    'id' => $news->getId()
                ));
            } catch (\DBALException $e) {
                $message = sprintf('DBALException [%i]: %s', $e->getCode(), $e->getMessage());
            } catch (\PDOException $e) {
                $message = sprintf('PDOException [%i]: %s', $e->getCode(), $e->getMessage());
            } catch (\ORMException $e) {
                $message = sprintf('ORMException [%i]: %s', $e->getCode(), $e->getMessage());
            } catch (\Exception $e) {
                $message = sprintf('Exception [%i]: %s', $e->getCode(), $e->getMessage());
            }

            $this->addFlash('error', $message);

            return $this->render('admin/page/edit.html.twig', [
                'object' => $news,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/page/edit.html.twig', [
            'object' => $news,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a News entity.
     */
    #[Route('/{id}/delete', name: 'admin_page_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $id, News $page)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_page_index');
        }

        $pageTitle = $page->getTitle();
        $pageId = $page->getId();

        $this->em->remove($page);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_PAGE,
            $pageId,
            $pageTitle
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_page_index');
    }

    #[Route('/disable', name: 'admin_page_disable', methods: ['POST'])]
    public function disableAction(Request $request)
    {
        $page = $this->em->getRepository(News::class)->find($request->request->get('newsId'));

        if ($page && $page->getPostType() === 'page') {
            $page->setEnable((bool) $request->request->get('enable'));
            $this->em->persist($page);
            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_TOGGLE,
                ActivityLog::ENTITY_PAGE,
                $page->getId(),
                $page->getTitle(),
                $page->getEnable() ? 'Bật hiển thị' : 'Tắt hiển thị'
            );
        }

        return new Response(
            json_encode(
                array(
                    'status' => 'success',
                    'message' => 'Thao tác thành công'
                )
            )
        );
    }

    /**
     * Handle _media_picker_url POST param: copy the selected media file
     * into the Vich upload dir and update entity->images (filename only).
     * Only acts when no new imageFile was uploaded (Vich takes precedence).
     */
    private function applyMediaPickerUrl(Request $request, News $news): void
    {
        // If a new file was uploaded via Vich, let Vich handle images — skip
        $uploadedFile = $request->files->get('page');
        if (!empty($uploadedFile['imageFile']['file'])) {
            return;
        }

        $pickerUrl = trim((string) $request->request->get('_media_picker_url', ''));
        if ($pickerUrl === '') {
            return;
        }

        $webRoot   = $this->getParameter('kernel.project_dir') . '/public';
        $sourcePath = $webRoot . '/' . ltrim($pickerUrl, '/');

        if (!is_file($sourcePath)) {
            return;
        }

        $destDir  = $webRoot . '/uploads/images/news/';
        $filename = basename($sourcePath);
        $destPath = $destDir . $filename;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Copy only if not already there
        if (!is_file($destPath)) {
            copy($sourcePath, $destPath);
        }

        // Set only filename — Vich uri_prefix handles the rest
        $news->setImages($filename);
    }

    private function getPageLevel(News $page)
    {
        $level = 0;
        $currentParent = $page->getParent();

        while (null !== $currentParent) {
            ++$level;
            $currentParent = $currentParent->getParent();
        }

        return $level;
    }
}

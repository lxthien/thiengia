<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\NewsCategory;
use App\Entity\News;
use App\Entity\Rating;
use App\Form\NewsCategoryType;
use App\Form\NewsType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller used to manage post contents in the backend.
 *
 * @Route("/admin/news")
 * @Security("has_role('ROLE_ADMIN')")
 */

class NewsController extends Controller
{
    /**
     * Lists all News entities.
     *
     * @Route("/", name="admin_news_index")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(News::class);
        $searchQuery = trim((string) $request->query->get('q', ''));

        if ($searchQuery !== '') {
            $news = $repository->searchPosts($searchQuery);
        } else {
            $news = $repository->findAllPosts();
        }

        return $this->render('admin/news/index.html.twig', [
            'objects' => $news,
            'search_query' => $searchQuery,
        ]);
    }

    /**
     * Lists all News entities by category.
     *
     * @Route("/list/{categoryId}", name="admin_news_list_by_category")
     * @Method("GET")
     */
    public function listAction(Request $request, $categoryId)
    {
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(News::class);
        $searchQuery = trim((string) $request->query->get('q', ''));

        if ($searchQuery !== '') {
            $news = $repository->searchPosts($searchQuery, $categoryId);
        } else {
            $news = $repository
                ->createQueryBuilder('n')
                ->leftJoin('n.category', 'c')
                ->where('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId)
                ->orderBy('n.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('admin/news/list.html.twig', [
            'objects' => $news,
            'search_query' => $searchQuery,
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Searches published posts and pages for the CKEditor content block tool.
     *
     * @Route("/content-block/related-search", name="admin_content_block_related_search", methods={"GET"})
     */
    public function relatedSearchAction(Request $request)
    {
        $query = trim((string) $request->query->get('q', ''));
        $currentId = $request->query->getInt('currentId', 0);

        if (mb_strlen($query) < 2) {
            return new JsonResponse(['items' => []]);
        }

        $items = $this->getDoctrine()->getRepository(News::class)
            ->createQueryBuilder('n')
            ->where('n.enable = :enabled')
            ->andWhere('n.id != :currentId')
            ->andWhere('n.title LIKE :query OR n.url LIKE :query OR n.description LIKE :query')
            ->setParameter('enabled', true)
            ->setParameter('currentId', $currentId)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'items' => array_map(function (News $news) {
                return [
                    'id' => $news->getId(),
                    'title' => $news->getTitle(),
                    'url' => $this->generateUrl('dynamic_post_page', ['slug' => $news->getUrl()]),
                    'description' => $news->getDescription(),
                ];
            }, $items),
        ]);
    }

    /**
     * Creates a new News entity.
     *
     * @Route("/new", name="admin_news_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request, Slugger $slugger)
    {
        $news = new News();
        $news->setAuthor($this->getUser());

        $form = $this->createForm(NewsType::class, $news)
            ->add('saveAndCreateNew', SubmitType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle Media Picker selection (bypasses Vich to avoid path conflicts)
                $this->applyMediaPickerUrl($request, $news);

                $em = $this->getDoctrine()->getManager();
                $em->persist($news);
                $em->flush();

                // Update Ordering for post
                $news->setOrdering( $news->getId() );
                $em = $this->getDoctrine()->getManager();
                $em->flush();

                // Activity Log
                $this->get(ActivityLogService::class)->log(
                    ActivityLog::ACTION_CREATE,
                    ActivityLog::ENTITY_NEWS,
                    $news->getId(),
                    $news->getTitle()
                );

                $this->addFlash('success', 'action.created_successfully');

                if ($form->get('saveAndCreateNew')->isClicked()) {
                    return $this->redirectToRoute('admin_news_new');
                }

                return $this->redirectToRoute('admin_news_edit', array(
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

            return $this->render('admin/news/new.html.twig', [
                'news' => $news,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/news/new.html.twig', [
            'news' => $news,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing News entity.
     *
     * @Route("/{id}/edit", requirements={"id": "\d+"}, name="admin_news_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, News $news, Slugger $slugger)
    {
        $form = $this->createForm(NewsType::class, $news);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle Media Picker selection (bypasses Vich to avoid path conflicts)
                $this->applyMediaPickerUrl($request, $news);

                $em = $this->getDoctrine()->getManager();
                $unitOfWork = $em->getUnitOfWork();
                $originalData = $unitOfWork->getOriginalEntityData($news);

                // Update createdAt if enable changed from false to true
                if (isset($originalData['enable']) && !$originalData['enable'] && $news->getEnable()) {
                    $news->setCreatedAt(new \DateTime());
                }

                // Handle postType change logic
                $originalPostType = isset($originalData['postType']) ? $originalData['postType'] : 'post';
                $newPostType = $news->getPostType();

                // From post to page
                if ($originalPostType === 'post' && $newPostType === 'page') {
                    // Remove relationships with categories and tags
                    $news->getCategory()->clear();
                    $news->getTags()->clear();
                    // Ensure parent is null for new pages
                    $news->setParent(null);
                }
                // From page to post
                elseif ($originalPostType === 'page' && $newPostType === 'post') {
                    // Remove parent relationship
                    $news->setParent(null);
                }

                // Capture changes before flush
                $diffDetails = $this->get(ActivityLogService::class)->getEntityDiff($news);

                $em->flush();

                // Activity Log
                $this->get(ActivityLogService::class)->log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::ENTITY_NEWS,
                    $news->getId(),
                    $news->getTitle(),
                    $diffDetails
                );

                $this->addFlash('success', 'action.updated_successfully');

                // If postType changed to page, redirect to page edit
                if ($originalPostType === 'post' && $newPostType === 'page') {
                    return $this->redirectToRoute('admin_page_edit', array(
                        'id' => $news->getId()
                    ));
                }

                return $this->redirectToRoute('admin_news_edit', array(
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

            return $this->render('admin/news/edit.html.twig', [
                'news' => $news,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/news/edit.html.twig', [
            'news' => $news,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a News entity.
     *
     * @Route("/{id}/delete", methods={"POST"}, name="admin_news_delete")
     */
    public function deleteAction(Request $request, $id, News $news)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_news_index');
        }

        $newsTitle = $news->getTitle();
        $newsId = $news->getId();

        $news->getTags()->clear();

        $em = $this->getDoctrine()->getManager();
        $em->remove($news);
        $em->flush();

        // Activity Log
        $this->get(ActivityLogService::class)->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_NEWS,
            $newsId,
            $newsTitle
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_news_index');
    }

    /**
     * @Route("/disable", name="admin_news_disable")
     */
    public function disableAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        $news = $this->getDoctrine()->getRepository(News::class)->find($request->request->get('newsId'));
        
        if ($news) {
            $news->setEnable($request->request->get('enable'));
        }

        $em->persist($news);

        $em->flush();

        // Activity Log
        $this->get(ActivityLogService::class)->log(
            ActivityLog::ACTION_TOGGLE,
            ActivityLog::ENTITY_NEWS,
            $news->getId(),
            $news->getTitle(),
            $news->getEnable() ? 'Bật hiển thị' : 'Tắt hiển thị'
        );

        return new Response(
            json_encode(
                array(
                    'status'=>'success',
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
        $uploadedFile = $request->files->get('news');
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
}

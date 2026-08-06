<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\Entity\NewsCategory;
use App\Entity\News;
use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Tag;
use App\Entity\Rating;
use App\Service\PageBuilderService;

use blackknight467\StarRatingBundle\Form\RatingType as RatingType;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use EWZ\Bundle\RecaptchaBundle\Validator\Constraints\IsTrue as RecaptchaTrue;

use App\Utils\ConvertImages;

class NewsController extends Controller
{
    /**
     * @var UploaderHelper
     */
    private $helper;
    private $convertImages;
    private $contentFormatter;
    private $viewCountLogger;
    private $pageBuilderService;

    /**
     * Constructs a new instance of UploaderExtension.
     *
     * @param UploaderHelper $helper
     */
    public function __construct(UploaderHelper $helper, ConvertImages $convertImages, \App\Service\ContentFormatter $contentFormatter, \App\Service\ViewCountLogger $viewCountLogger, PageBuilderService $pageBuilderService)
    {
        $this->helper = $helper;
        $this->convertImages = $convertImages;
        $this->contentFormatter = $contentFormatter;
        $this->viewCountLogger = $viewCountLogger;
        $this->pageBuilderService = $pageBuilderService;
    }

    /**
     * Dynamic route handler - intelligently routes to correct action based on URL slugs
     * 
     * Handles patterns:
     * - /post-slug/ (post/page)
     * - /category-slug/ (category list)
     * - /category-slug/post-slug/ (post in category)
     * - /parent-category/child-category/ (sub-category list)
     * - /parent-category/child-category/post-slug/ (post in sub-category)
     * 
     * @param string $slug First URL segment
     * @param string $level1 First segment (alias for slug in some routes)
     * @param string $level2 Second segment
     * @param string $level3 Third segment
     * @param Request $request
     * @return Response
     */
    public function dynamicRouteAction($slug = null, $level1 = null, $level2 = null, $level3 = null, Request $request)
    {
        // Normalize parameters
        if (empty($slug) && !empty($level1)) {
            $slug = $level1;
        }

        // Single segment pattern: /slug/
        if (!empty($slug) && empty($level2) && empty($level3)) {
            return $this->handleSingleSegment($slug, $request);
        }

        // Two segment pattern: /segment1/segment2/
        if (!empty($slug) && !empty($level2) && empty($level3)) {
            return $this->handleTwoSegments($slug, $level2, $request);
        }

        // Three segment pattern: /segment1/segment2/segment3/
        if (!empty($slug) && !empty($level2) && !empty($level3)) {
            return $this->handleThreeSegments($slug, $level2, $level3, $request);
        }

        throw $this->createNotFoundException("Invalid URL format");
    }

    /**
     * Handle single segment URLs: /slug/
     * Could be: post, page, or category
     * 
     * Priority:
     * 1. Check if it's a post/page
     * 2. Check if it's a category (then call listAction)
     * 3. Not found error
     */
    private function handleSingleSegment($slug, Request $request)
    {
        // First, try to find it as a post/page
        if ($request->query->get('preview') === false || $request->query->get('preview_id') === null) {
            $post = $this->getDoctrine()
                ->getRepository(News::class)
                ->findOneBy(['url' => $slug, 'enable' => 1]);
        } else {
            $post = $this->getDoctrine()
                ->getRepository(News::class)
                ->find($request->query->get('preview_id'));
        }

        if ($post) {
            // It's a post/page - forward to showAction
            return $this->showAction($slug, $request);
        }

        // Try to find it as a category
        $category = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->findOneBy(['url' => $slug, 'enable' => 1]);

        if ($category) {
            // Check if this is a child category
            if ($category->getParentcat() !== 'root') {
                // It's a child category - redirect to proper hierarchical URL
                return $this->redirectToRoute('dynamic_category_post', array(
                    'level1' => $category->getParentcat()->getUrl(),
                    'level2' => $category->getUrl()
                ), 301);
            }

            // It's a top-level category - forward to listAction
            return $this->listAction($slug, null, 1, $request);
        }

        // Not found
        throw $this->createNotFoundException("The item does not exist");
    }

    /**
     * Handle two segment URLs: /segment1/segment2/
     * Could be:
     * 1. /category/post/ - post in a category
     * 2. /parent-category/child-category/ - sub-category list
     */
    private function handleTwoSegments($level1, $level2, Request $request)
    {
        // First, try to find level1 as a category (parent)
        $parentCategory = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->findOneBy(['url' => $level1, 'enable' => 1]);

        if (!$parentCategory) {
            throw $this->createNotFoundException("Category not found: $level1");
        }

        // Now check if level2 is a child category
        $childCategory = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->findOneBy(['url' => $level2, 'parentcat' => $parentCategory->getId(), 'enable' => 1]);

        if ($childCategory) {
            // It's a child category - forward to listAction
            return $this->listAction($level1, $level2, 1, $request);
        }

        // Check if level2 is a post in this category
        $post = $this->getDoctrine()
            ->getRepository(News::class)
            ->findOneBy(['url' => $level2, 'enable' => 1]);

        if ($post) {
            // Verify that the post belongs to this category
            $categories = $post->getCategory();
            foreach ($categories as $category) {
                if (
                    $category->getId() === $parentCategory->getId() ||
                    ($category->getParentcat() && $category->getParentcat()->getId() === $parentCategory->getId())
                ) {
                    // It's a post in this category - show the post
                    return $this->showAction($level2, $request);
                }
            }
            // Post exists but not in this category context, show it anyway
            return $this->showAction($level2, $request);
        }

        // level2 is neither a child category nor a post - might be pagination or doesn't exist
        throw $this->createNotFoundException("Post or category not found: $level2");
    }

    /**
     * Handle three segment URLs: /segment1/segment2/segment3/
     * Pattern: /parent-category/child-category/post/ - post in a sub-category
     */
    private function handleThreeSegments($level1, $level2, $level3, Request $request)
    {
        // Find parent category
        $parentCategory = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->findOneBy(['url' => $level1, 'enable' => 1]);

        if (!$parentCategory) {
            throw $this->createNotFoundException("Parent category not found: $level1");
        }

        // Find child category
        $childCategory = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->findOneBy(['url' => $level2, 'parentcat' => $parentCategory->getId(), 'enable' => 1]);

        if (!$childCategory) {
            throw $this->createNotFoundException("Child category not found: $level2");
        }

        // Find post by slug
        $post = $this->getDoctrine()
            ->getRepository(News::class)
            ->findOneBy(['url' => $level3, 'enable' => 1]);

        if ($post) {
            // Verify post belongs to child category or its parent
            $categories = $post->getCategory();
            foreach ($categories as $category) {
                if ($category->getId() === $childCategory->getId()) {
                    return $this->showAction($level3, $request);
                }
            }
            // Post exists but not in this category path, show it anyway
            return $this->showAction($level3, $request);
        }

        // Check if level3 is another category (for nesting support)
        $nextChildCategory = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->findOneBy(['url' => $level3, 'parentcat' => $childCategory->getId(), 'enable' => 1]);

        if ($nextChildCategory) {
            // This would require 4 segments, which we don't support yet
            // For now, treat as not found
            throw $this->createNotFoundException("Post not found: $level3");
        }

        throw $this->createNotFoundException("Post or category not found: $level3");
    }

    /**
     * Render the list posts by the category
     * 
     * @return News
     */
    public function listAction($level1, $level2 = null, $page = 1, Request $request)
    {
        $page = $request->query->getInt('page', $page);
        if ($page < 1) {
            $page = 1;
        }

        $category = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->findOneBy(array('url' => $level1, 'enable' => 1));

        if (!$category) {
            throw $this->createNotFoundException("The item does not exist");
        }

        // If this is a child category accessed directly (shouldn't happen with dynamic routing, but check anyway)
        if ($category->getParentcat() !== 'root') {
            return $this->redirectToRoute('dynamic_category_post', array('level1' => $category->getParentcat()->getUrl(), 'level2' => $category->getUrl()), 301);
        }

        if (!empty($level2)) {
            $subCategory = $this->getDoctrine()
                ->getRepository(NewsCategory::class)
                ->findOneBy(array('url' => $level2, 'enable' => 1));

            if (!$subCategory) {
                throw $this->createNotFoundException("The item does not exist");
            }

            // Verify that $level2 is actually a child of $level1
            if ($subCategory->getParentcat() === null || $subCategory->getParentcat()->getId() != $category->getId()) {
                throw $this->createNotFoundException("The item does not exist");
            }
        }

        $danhMuc = $request->query->get('danh-muc');

        if (!empty($danhMuc)) {
            if (!empty($level2)) {
                return $this->redirectToRoute('dynamic_category_post', array('level1' => $level1, 'level2' => $level2), 301);
            } else {
                return $this->redirectToRoute('dynamic_post_page', array('slug' => $level1), 301);
            }
        }

        // Init breadcrum for category page
        $this->buildBreadcrums(!empty($level2) ? $subCategory : $category, null, null);

        $ordering = $category->getSortBy() == null ? '{"createdAt":"DESC"}' : $category->getSortBy();
        $orderingData = (array) (json_decode($ordering));
        $orderingKey = array_keys($orderingData);

        $listCategories = array();

        if (empty($level2)) {
            // Get all post for this category and sub category
            $listCategoriesIds[] = $category->getId();

            $allSubCategories = $this->getDoctrine()
                ->getRepository(NewsCategory::class)
                ->getSubCategories($category->getId());

            foreach ($allSubCategories as $value) {
                $listCategories[] = $value;
                $listCategoriesIds[] = $value->getId();
            }

            if ($category->getContent() == NULL) {
                $news = $this->getDoctrine()
                    ->getRepository(News::class)
                    ->getNewsByCategories($listCategoriesIds, $orderingKey[0], $orderingData[$orderingKey[0]]);
            } else {
                $news = $this->getDoctrine()
                    ->getRepository(News::class)
                    ->getNewsByCategory($category->getId(), $orderingKey[0], $orderingData[$orderingKey[0]], 8);
            }
        } else {
            if ($subCategory->getContent() == NULL) {
                $news = $this->getDoctrine()
                    ->getRepository(News::class)
                    ->getNewsByCategory($subCategory->getId(), $orderingKey[0], $orderingData[$orderingKey[0]]);
            } else {
                $news = $this->getDoctrine()
                    ->getRepository(News::class)
                    ->getNewsByCategory($subCategory->getId(), $orderingKey[0], $orderingData[$orderingKey[0]], 12);
            }
        }

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $news,
            $page,
            $this->get('settings_manager')->get('numberRecordOnPage') ?: 10
        );

        return $this->render('news/list.html.twig', [
            'baseUrl' => !empty($level2) ? $this->generateUrl('dynamic_category_post', array('level1' => $level1, 'level2' => $level2), UrlGeneratorInterface::ABSOLUTE_URL) : $this->generateUrl('dynamic_post_page', array('slug' => $level1), UrlGeneratorInterface::ABSOLUTE_URL),
            'category' => !empty($level2) ? $subCategory : $category,
            'listCategories' => count($listCategories) > 0 ? $listCategories : NULL,
            'pagination' => $pagination
        ]);
    }

    /**
     * Display a post/page
     * 
     * Note: Route is defined in app/config/routing.yml as both 'news_show' (legacy)
     * and 'dynamic_post_page' (preferred). All routing goes through dynamicRoute().
     */
    public function showAction($slug, Request $request)
    {
        $isPreview = $request->query->has('preview') && $request->query->has('preview_id');
        
        // 1. Bảo mật: Chỉ ADMIN mới được xem bản nháp/preview
        if ($isPreview) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException("Bạn không có quyền xem bản nháp.");
            }
            $post = $this->getDoctrine()->getRepository(News::class)->find($request->query->get('preview_id'));
        } else {
            $post = $this->getDoctrine()->getRepository(News::class)->findOneBy(['url' => $slug, 'enable' => 1]);
        }

        if (!$post) {
            throw $this->createNotFoundException("Nội dung không tồn tại.");
        }

        // 2. HTTP Caching: Tăng tốc cho người dùng quay lại và giảm tải Server
        $response = new Response();
        $response->setEtag(md5($post->getId() . $post->getUpdatedAt()->getTimestamp() . $this->getParameter('app.cache_version')));
        $response->setPublic();
        if ($response->isNotModified($request)) {
            return $response;
        }

        // 3. SEO Canonical Redirect (301)
        if ($request->query->has('danh-muc')) {
            return $this->redirectToRoute('news_show', ['slug' => $slug], 301);
        }

        // Async log viewCount
        $this->viewCountLogger->logView($post->getId(), $request);

        // 4. Xử lý Category & Related News
        $category = null;
        $relatedNews = [];
        $categoryUrl = null;
        
        // Determine primary category
        $categoryPrimaryId = $post->getCategoryPrimary() ?: (!$post->getCategory()->isEmpty() ? $post->getCategory()[0]->getId() : null);

        if ($categoryPrimaryId) {
            $category = $this->getDoctrine()->getRepository(NewsCategory::class)->find($categoryPrimaryId);
            if ($category) {
                // Optimize sorting logic
                $ordering = json_decode($category->getSortBy() ?: '{"createdAt":"DESC"}', true);
                $sortKey = key($ordering);
                
                $relatedNews = $this->getDoctrine()->getRepository(News::class)
                    ->getRelatedNews($categoryPrimaryId, $post->getId(), $post->getPostType(), $sortKey, $ordering[$sortKey], 12);
                
                // Build category URL
                if ($category->getParentcat() === 'root') {
                    $categoryUrl = $this->generateUrl("dynamic_post_page", ['slug' => $category->getUrl()], UrlGeneratorInterface::ABSOLUTE_URL);
                } else {
                    $categoryUrl = $this->generateUrl("dynamic_category_post", [
                        'level1' => $category->getParentcat()->getUrl(), 
                        'level2' => $category->getUrl()
                    ], UrlGeneratorInterface::ABSOLUTE_URL);
                }
            }
        }

        // 5. Thu thập dữ liệu khác
        $comments = $this->getDoctrine()->getRepository(Comment::class)->getApprovedCommentsForNews($post->getId());
        $rating = $this->getDoctrine()->getRepository(Rating::class)->getAverageRatingForNews($post->getId());
        $pageBuilderBlocks = [];
        $contentsLazy = $this->contentFormatter->lazyloadContent($post);

        if ($post->isPage() && $post->isPageBuilderEnabled()) {
            $pageBuilderBlocks = $this->pageBuilderService->parseBlocks($post->getPageBuilderData());

            if (!empty($pageBuilderBlocks)) {
                $contentsLazy = null;
            }
        }
        
        // Tối ưu Image Size (Vẫn dùng filesystem nhưng ltrim cho an toàn)
        $imagePath = $this->helper->asset($post, 'imageFile');
        $imageSize = $imagePath ? @getimagesize(ltrim($imagePath, '/')) : null;

        // Form Comment & Rating
        $form = $this->renderFormComment($post);
        $formRating = $this->createForm(\App\Form\PostRatingType::class, null, [
            'action' => $this->generateUrl('rating')
        ]);

        // Build breadcrumbs
        $this->buildBreadcrums(null, $post->isPage() ? null : $post, $post->isPage() ? $post : null);

        // Dữ liệu chung cho View
        $viewData = [
            'post' => $post,
            'contentsLazy' => $contentsLazy,
            'pageBuilderBlocks' => $pageBuilderBlocks,
            'contactBlockForm' => $post->isPage() && $this->pageBuilderService->hasBlockType($pageBuilderBlocks, 'contact_form')
                ? $this->renderPageBuilderContactForm()->createView()
                : null,
            'form' => $form->createView(),
            'formRating' => $formRating->createView(),
            'rating' => !empty($rating['ratingValue']) ? str_replace('.0', '', number_format($rating['ratingValue'], 1)) : 0,
            'ratingPercent' => !empty($rating['ratingValue']) ? str_replace('.00', '', number_format(($rating['ratingValue'] * 100) / 5, 2)) : 0,
            'ratingValue' => round($rating['ratingValue'] ?? 0),
            'ratingCount' => round($rating['ratingCount'] ?? 0),
            'comments' => $comments,
            'imageSize' => $imageSize,
        ];

        if ($post->isPage()) {
            return $this->render('news/page.html.twig', $viewData, $response);
        }

        // Dữ liệu đặc thù cho Post/News
        $plainContent = $this->contentFormatter->stripTagsContent($contentsLazy);
        $viewData += [
            'articleBody' => $plainContent,
            'wordCount' => str_word_count($plainContent),
            'relatedNews' => !empty($relatedNews) ? $relatedNews : null,
            'category' => $category,
            'categoryUrl' => $categoryUrl,
            'urlParameters' => null
        ];

        return $this->render('news/show.html.twig', $viewData, $response);
    }



    /**
     * @Route("/tag/{slug}",
     *      name="tags",
     *      requirements={
     *          "slug": "[^\n]+"
     *      }))
     */
    public function tagAction($slug, Request $request)
    {
        $tag = $this->getDoctrine()
            ->getRepository(Tag::class)
            ->findOneBy(
                array('url' => $slug)
            );

        if (!$tag) {
            throw $this->createNotFoundException("Tag not found");
        }

        // Get the list post related to tag
        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->getNewsByTag($tag->getId(), null);

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $posts,
            !empty($request->query->get('page')) ? $request->query->get('page') : 1,
            40
        );

        $breadcrumbs = $this->get("white_october_breadcrumbs");
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        $breadcrumbs->addItem($tag->getName());

        return $this->render('news/tags.html.twig', [
            'baseUrl' => $this->generateUrl('tags', array('slug' => $slug), UrlGeneratorInterface::ABSOLUTE_URL),
            'tag' => $tag,
            'pagination' => $pagination
        ]);
    }

    /**
     * Render list recent news
     * @return News
     */
    public function recentNewsAction()
    {
        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->findBy(
                array('postType' => 'post', 'enable' => 1),
                array('createdAt' => 'DESC'),
                20
            );

        $response = $this->render('news/recent.html.twig', [
            'posts' => $posts,
        ]);

        // cache for 3600 seconds
        $response->setSharedMaxAge(3600);

        // (optional) set a custom Cache-Control directive
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    /**
     * Render list hot news
     * @return News
     */
    public function hotNewsAction()
    {
        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->findBy(
                array('postType' => 'post', 'enable' => 1),
                array('viewCounts' => 'DESC'),
                20
            );

        $response = $this->render('news/hot.html.twig', [
            'posts' => $posts,
        ]);

        // cache for 3600 seconds
        $response->setSharedMaxAge(3600);

        // (optional) set a custom Cache-Control directive
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    /**
     * Render list related news in sidebar
     * @return News
     */
    public function relatedNewsAction($relatedNews)
    {
        $sidebarPostsArray = array();

        if (!empty($relatedNews)) {
            $listPosts = explode(',', $relatedNews);

            for ($i = 0; $i < count($listPosts); $i++) {
                $post = $this->getDoctrine()
                    ->getRepository(News::class)
                    ->find($listPosts[$i]);
                if ($post) {
                    $sidebarPostsArray[] = $post;
                }
            }

            return $this->render('news/relatedNews.html.twig', [
                'posts' => $sidebarPostsArray
            ]);
        }
    }

    public function sidebarPostsAction($sidebarPosts)
    {
        $sidebarPostsArray = array();

        if (!empty($sidebarPosts)) {
            $sidebarPostsObject = json_decode($sidebarPosts);

            if (is_object($sidebarPostsObject)) {
                $listPosts = explode(',', $sidebarPostsObject->IDs);

                for ($i = 0; $i < count($listPosts); $i++) {
                    $post = $this->getDoctrine()
                        ->getRepository(News::class)
                        ->find($listPosts[$i]);
                    if ($post) {
                        $sidebarPostsArray[] = $post;
                    }
                }
            }

            return $this->render('news/sidebarPosts.html.twig', [
                'title' => $sidebarPostsObject->title,
                'posts' => $sidebarPostsArray
            ]);
        }
    }

    /**
     * Render list news by category
     * @return News
     */
    public function listNewsByCategoryAction($categoryId)
    {
        $category = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->find($categoryId);

        $listCategoriesIds = array($category->getId());
        $allSubCategories = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->createQueryBuilder('c')
            ->where('c.parentcat = (:parentcat)')
            ->setParameter('parentcat', $category->getId())
            ->getQuery()->getResult();

        foreach ($allSubCategories as $value) {
            $listCategoriesIds[] = $value->getId();
        }

        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->createQueryBuilder('n')
            ->innerJoin('n.category', 't')
            ->where('t.id IN (:listCategoriesIds)')
            ->andWhere('n.enable = :enable')
            ->setParameter('listCategoriesIds', $listCategoriesIds)
            ->setParameter('enable', 1)
            ->setMaxResults(10)
            ->orderBy('n.viewCounts', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('news/listByCategory.html.twig', [
            'posts' => $posts,
        ]);
    }

    public function listNewsByCategorySidebarAction($categoryId, $title, $postNumber = 10)
    {
        $category = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->find($categoryId);

        $listCategoriesIds = array($category->getId());
        $allSubCategories = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->createQueryBuilder('c')
            ->where('c.parentcat = (:parentcat)')
            ->setParameter('parentcat', $category->getId())
            ->getQuery()->getResult();

        foreach ($allSubCategories as $value) {
            $listCategoriesIds[] = $value->getId();
        }

        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->createQueryBuilder('n')
            ->innerJoin('n.category', 't')
            ->where('t.id IN (:listCategoriesIds)')
            ->andWhere('n.enable = :enable')
            ->setParameter('listCategoriesIds', $listCategoriesIds)
            ->setParameter('enable', 1)
            ->setMaxResults($postNumber)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('news/sidebarPosts.html.twig', [
            'title' => $title,
            'posts' => $posts
        ]);
    }

    /**
     * @Route("/rating", name="rating")
     * 
     * @return JSON
     */
    public function ratingAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $rating = new Rating();
        $rating->setNewsId($request->request->get('newsId'));
        $rating->setRating($request->request->get('rating'));

        $em->persist($rating);

        $em->flush();

        return new Response(
            json_encode(
                array(
                    'status' => 'success',
                    'message' => 'Cảm ơn đánh giá của bạn'
                )
            )
        );
    }

    /**
     * @Route("/search", name="news_search")
     * 
     * @return News
     */
    public function handleSearchFormAction(Request $request)
    {
        $page = !empty($request->query->get('page')) ? $request->query->get('page') : 1;

        $form = $this->createFormBuilder(null, array(
            'csrf_protection' => false,
        ))
            ->setAction($this->generateUrl('news_search'))
            ->setMethod('POST')
            ->add('q', TextType::class)
            ->add('search', ButtonType::class, array('label' => 'Search'))
            ->getForm();

        $form->handleRequest($request);

        if (!$form->isSubmitted() && empty($request->query->get('q'))) {
            return $this->redirectToRoute('homepage', [], 301);
        }

        $q = $form->getData()['q'];
        if (!empty($q)) {
            return $this->redirectToRoute('news_search', array('q' => $q));
        }

        // Improved search: search across multiple fields
        $searchTerm = $request->query->get('q');
        $searchPattern = '%' . $searchTerm . '%';

        $query = $this->getDoctrine()
            ->getRepository(News::class)
            ->createQueryBuilder('p')
            ->where('p.title LIKE :q 
                    OR p.description LIKE :q 
                    OR p.pageTitle LIKE :q 
                    OR p.pageDescription LIKE :q 
                    OR p.pageKeyword LIKE :q')
            ->andWhere('p.enable = :enable')
            ->setParameter('q', $searchPattern)
            ->setParameter('enable', 1)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery();

        $results = $query->getResult();

        // Highlight search keywords in results
        $highlightedResults = [];
        foreach ($results as $result) {
            $result->highlightedTitle = $this->highlightKeyword($result->getTitle(), $searchTerm);
            $result->highlightedDescription = $this->highlightKeyword($result->getDescription(), $searchTerm);
            $highlightedResults[] = $result;
        }

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $highlightedResults,
            $page,
            $this->get('settings_manager')->get('numberRecordOnPage') ?: 10
        );

        $breadcrumbs = $this->get("white_october_breadcrumbs");
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        $breadcrumbs->addItem('search');
        $breadcrumbs->addItem(ucfirst($searchTerm));

        return $this->render('news/search.html.twig', [
            'baseUrl' => $this->generateUrl('news_search', array('q' => $searchTerm), UrlGeneratorInterface::ABSOLUTE_URL),
            'q' => ucfirst($searchTerm),
            'pagination' => $pagination
        ]);
    }

    /**
     * Highlight search keyword in text with <mark> tags
     * 
     * @param string $text The text to highlight
     * @param string $keyword The keyword to highlight
     * @return string The highlighted text
     */
    private function highlightKeyword($text, $keyword)
    {
        if (empty($text) || empty($keyword)) {
            return $text;
        }

        // Case-insensitive replacement with word boundaries
        $pattern = '/(' . preg_quote($keyword, '/') . ')/i';
        return preg_replace($pattern, '<mark>$1</mark>', $text);
    }

    /**
     * Render the form comment of news
     * 
     * @return Form
     **/
    private function renderFormComment($post)
    {
        $comment = new Comment();
        $comment->setIp($this->container->get('request_stack')->getCurrentRequest()->getClientIp());
        $comment->setNewsId($post->getId());

        $form = $this->createFormBuilder($comment)
            ->setAction($this->generateUrl('handle_comment_form'))
            ->add('content', TextareaType::class, array(
                'required' => true,
                'label' => 'label.content',
                'attr' => array('rows' => '7')
            ))
            ->add('author', TextType::class, array('label' => 'label.author'))
            ->add('phone', TextType::class, array('label' => 'Số điện thoại'))
            ->add('ip', HiddenType::class)
            ->add('news_id', HiddenType::class)
            ->add('comment_id', HiddenType::class)
            ->add('gclid', HiddenType::class, array('required' => false))
            ->add('send', ButtonType::class, array('label' => 'label.send'))
            ->getForm();

        return $form;
    }

    /**
     * Handle form comment for post
     * 
     * @return JSON
     **/
    public function handleCommentFormAction(Request $request, \Swift_Mailer $mailer)
    {
        if (!$request->isXmlHttpRequest()) {
            return new Response(
                json_encode(
                    array(
                        'status' => 'error',
                        'message' => 'You can access this only using Ajax!'
                    )
                )
            );
        } else {
            $comment = new Comment();

            $form = $this->createFormBuilder($comment)
                ->add('content', TextareaType::class)
                ->add('author', TextType::class)
                ->add('phone', TextType::class)
                ->add('ip', HiddenType::class)
                ->add('news_id', HiddenType::class)
                ->add('comment_id', HiddenType::class)
                ->add('gclid', HiddenType::class, array('required' => false))
                ->getForm();

            $form->handleRequest($request);

            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($comment);
                $em->flush();

                if (null !== $comment->getId()) {
                    /*
                    $message = (new \Swift_Message())
                        ->setSubject($this->get('translator')->trans('comment.email.title', ['%siteName%' => $this->get('settings_manager')->get('siteName')]))
                        ->setFrom(['hotro.xaydungkimanh@gmail.com' => $this->get('settings_manager')->get('siteName')])
                        ->setTo($this->get('settings_manager')->get('emailContact'))
                        ->setBody(
                            $this->renderView(
                                'Emails/comment.html.twig',
                                array(
                                    'name' => $request->request->get('form')['author'],
                                    'body' => $request->request->get('form')['content'],
                                    'gclid' => $comment->getGclid()
                                )
                            ),
                            'text/html'
                        )
                    ;

                    $mailer->send($message);
                    */

                    return new Response(
                        json_encode(
                            array(
                                'status' => 'success',
                                'message' => '<div class="alert alert-success" role="alert">' . $this->get('translator')->trans('comment.thank_for_your_comment') . '</div>'
                            )
                        )
                    );
                } else {
                    return new Response(
                        json_encode(
                            array(
                                'status' => 'error',
                                'message' => '<div class="alert alert-warning" role="alert">' . $this->get('translator')->trans('comment.have_a_problem_on_your_request') . '</div>'
                            )
                        )
                    );
                }
            } else {
                return new Response(
                    json_encode(
                        array(
                            'status' => 'error',
                            'message' => '<div class="alert alert-warning" role="alert">' . $this->get('translator')->trans('comment.have_a_problem_on_your_request') . '</div>'
                        )
                    )
                );
            }
        }
    }

    /**
     * Handle the breadcrumb
     * 
     * @return Breadcrums
     **/
    private function buildBreadcrums($category = null, $post = null, $page = null, $categoryPrimary = null)
    {
        // Init october breadcrum
        $breadcrumbs = $this->get("white_october_breadcrumbs");

        // Add home item into first breadcrum.
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));

        // Breadcrum for page (with parent hierarchy)
        if (!empty($page) && $page->isPage()) {
            // Add "Dịch vụ" item without link for specific pages
            /* if ($page->getTemplate() == "service_page") {
                $breadcrumbs->addItem("Dịch vụ", $this->generateUrl("homepage") . '#dich-vu');
            } */

            // Build the parent chain for the page
            $pageChain = [];
            $currentPage = $page;

            // Collect all parents in reverse order
            while ($currentPage) {
                $pageChain[] = $currentPage;
                $currentPage = $currentPage->getParent();
            }

            // Reverse to get the correct order (parent -> child)
            $pageChain = array_reverse($pageChain);

            // Add each page in the chain to breadcrumbs
            foreach ($pageChain as $index => $p) {
                // Add "Dịch vụ" item without link for specific pages
                if ($index === 0 && $p->getTemplate() == "service_page") {
                    $breadcrumbs->addItem("Dịch vụ", $this->generateUrl("homepage") . '#dich-vu');
                }

                // Use breadcrumb_title if available (shorter label), otherwise use title
                $breadcrumbLabel = method_exists($p, 'getBreadcrumbTitle') && !empty($p->getBreadcrumbTitle())
                    ? $p->getBreadcrumbTitle()
                    : $p->getTitle();

                if ($index < count($pageChain) - 1) {
                    // Add as link for parent pages
                    $breadcrumbs->addItem($breadcrumbLabel, $this->generateUrl('news_show', array('slug' => $p->getUrl())));
                } else {
                    // Add current page without link
                    $breadcrumbs->addItem($p->getTitle(), $this->generateUrl('news_show', array('slug' => $p->getUrl())));
                }
            }

            return $breadcrumbs;
        }

        // Breadcrum for category page
        if (!empty($category)) {
            if ($category->getParentcat() === 'root') {
                $breadcrumbs->addItem($category->getName(), $this->generateUrl("dynamic_post_page", array('slug' => $category->getUrl())));
            } else {
                $breadcrumbs->addItem($category->getParentcat()->getName(), $this->generateUrl("dynamic_post_page", array('slug' => $category->getParentcat()->getUrl())));
                $breadcrumbs->addItem($category->getName(), $this->generateUrl("dynamic_category_post", array('level1' => $category->getParentcat()->getUrl(), 'level2' => $category->getUrl())));
            }
        }

        // Breadcrum for post page
        if (!empty($post)) {
            $category;

            if (!$categoryPrimary) {
                $categoryPrimary = $post->getCategoryPrimary();
                if ($categoryPrimary > 0) {
                    $category = $this->getDoctrine()
                        ->getRepository(NewsCategory::class)
                        ->find($categoryPrimary);
                } else {
                    if (!$post->getCategory()->isEmpty()) {
                        $category = $post->getCategory()[0];
                    }
                }
            } else {
                $category = $this->getDoctrine()
                    ->getRepository(NewsCategory::class)
                    ->find($categoryPrimary);
            }

            if (!empty($category)) {
                if ($category->getParentcat() === 'root') {
                    $breadcrumbs->addItem($category->getName(), $this->generateUrl("dynamic_post_page", array('slug' => $category->getUrl())));
                    $breadcrumbs->addItem($post->getTitle(), $this->generateUrl('dynamic_post_page', array('slug' => $post->getUrl())));
                } else {
                    $parentCategory = $category->getParentcat();
                    $breadcrumbs->addItem($parentCategory->getName(), $this->generateUrl("dynamic_post_page", array('slug' => $parentCategory->getUrl())));
                    $breadcrumbs->addItem($category->getName(), $this->generateUrl("dynamic_category_post", array('level1' => $parentCategory->getUrl(), 'level2' => $category->getUrl())));
                    $breadcrumbs->addItem($post->getTitle(), $this->generateUrl('dynamic_post_page', array('slug' => $post->getUrl())));
                }
            } else {
                $breadcrumbs->addItem($post->getTitle(), $this->generateUrl('dynamic_post_page', array('slug' => $post->getUrl())));
            }
        }

        return $breadcrumbs;
    }

    private function renderPageBuilderContactForm()
    {
        $contact = new Contact();

        return $this->get('form.factory')->createNamedBuilder('page_builder_contact', FormType::class, $contact, [
            'action' => $this->generateUrl('page_builder_contact_submit'),
            'method' => 'POST',
        ])
            ->add('name', TextType::class, ['label' => 'Họ và tên *'])
            ->add('phone', TextType::class, ['label' => 'Số điện thoại *'])
            ->add('email', EmailType::class, ['label' => 'Email (không bắt buộc)', 'required' => false])
            ->add('contents', TextareaType::class, [
                'label' => 'Nội dung yêu cầu tư vấn *',
                'attr' => ['rows' => '5'],
            ])
            ->add('gclid', HiddenType::class, ['required' => false])
            ->add('send', SubmitType::class, [
                'label' => 'Gửi yêu cầu tư vấn',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();
    }

        /**
     * @Route("/chi-phi-xay-dung/", name="caculator_cost_construction")
     * 
     */
    public function caculatorCostConstructionAction($type = null, Request $request)
    {
        $settingsManager = $this->get('settings_manager');

        $form = $this->createFormBuilder(null, array(
            'csrf_protection' => false,
        ))
            ->setAction($this->generateUrl('caculator_cost_construction'))
            ->setMethod('POST')
            ->add('type', ChoiceType::class, array(
                'choices' => array(
                    'Nhà phố' => 1,
                    'Biệt thự' => 2,
                    'Nhà cấp 4' => 3,
                ),
                'label' => 'Chọn loại nhà'
            ))
            ->add('method', ChoiceType::class, array(
                'choices' => array(
                    'Xây nhà phần thô' => 1,
                    'Xây nhà trọn gói' => 2,
                ),
                'label' => 'Chọn hình thức thi công'
            ))
            ->add('muc_dau_tu', ChoiceType::class, array(
                'choices' => array(
                    'Trung bình' => 1,
                    'TB - Khá' => 2,
                    'Khá +' => 3,
                ),
                'label' => 'Mức đầu tư'
            ))
            ->add('wide', TextType::class, array(
                'label' => 'Chiều rộng (m)',
                'attr' => array(
                    'placeholder' => 'VD: Nhập 4 hoặc 4.5'
                )
            ))
            ->add('long', TextType::class, array(
                'label' => 'Chiều dài (m)',
                'attr' => array(
                    'placeholder' => 'VD: Nhập 12 hoặc 12.3'
                )
            ))
            ->add('floor', ChoiceType::class, array(
                'choices' => array(
                    '1' => 1, '2' => 2, '3' => 3, '4' => 4,
                    '5' => 5, '6' => 6, '7' => 7,
                ),
                'label' => 'Chọn số tầng'
            ))
            ->add('mong', ChoiceType::class, array(
                'choices' => array(
                    'Móng cọc (Móng đài)' => 1,
                    'Móng băng' => 2,
                    'Móng đơn' => 3,
                ),
                'label' => 'Chọn loại móng'
            ))
            ->add('mai', ChoiceType::class, array(
                'choices' => array(
                    'Mái BTCT đúc bằng' => 1,
                    'Mái lợp tôn lạnh' => 2,
                    'Mái xà gồ thép lợp ngói' => 3,
                    'Mái BTCT lợp ngói' => 4,
                ),
                'label' => 'Chọn loại mái'
            ))
            ->add('mat_tien', ChoiceType::class, array(
                'choices' => array(
                    '1 mặt tiền' => 1,
                    '2 mặt tiền' => 2,
                ),
                'label' => 'Mặt tiền'
            ))
            ->add('hem', ChoiceType::class, array(
                'choices' => array(
                    'Lớn hơn 5m' => 1,
                    'Hẻm 3m - 5m' => 2,
                    'Hẻm nhỏ hơn 3m' => 3,
                ),
                'label' => 'Điều kiện thi công (Hẻm)'
            ))
            ->add('lung', ChoiceType::class, array(
                'choices' => array('Không' => 0, 'Có' => 1),
                'label' => 'Tầng lửng'
            ))
            ->add('tum', ChoiceType::class, array(
                'choices' => array('Không' => 0, 'Có' => 1),
                'label' => 'Tum / Tầng thượng'
            ))
            ->add('san_thuong', ChoiceType::class, array(
                'choices' => array(
                    'Không' => 0,
                    'Sân thượng không mái' => 1,
                    'Sân thượng có mái che' => 2,
                ),
                'label' => 'Sân thượng'
            ))
            ->add('ban_cong', ChoiceType::class, array(
                'choices' => array('Không' => 0, 'Có' => 1),
                'label' => 'Ban công'
            ))
            ->add('tang_ham', ChoiceType::class, array(
                'choices' => array(
                    'Không có' => 0,
                    'Sâu 1.0m - 1.2m' => 1,
                    'Sâu 1.2m - 1.5m' => 2,
                    'Sâu 1.5m - 1.7m' => 3,
                    'Sâu 1.7m - 2.0m' => 4,
                    'Sâu 2.0m - 2.5m' => 5,
                    'Sâu > 2.5m' => 6,
                ),
                'label' => 'Tầng hầm'
            ))
            ->add('san_vuon', ChoiceType::class, array(
                'choices' => array('Không' => 0, 'Có' => 1),
                'label' => 'Sân vườn'
            ))
            ->add('reset', ResetType::class, array(
                'label' => 'Làm lại'
            ))
            ->add('caculator', SubmitType::class, array(
                'label' => 'Dự toán chi phí'
            ))
            ->getForm();

        $form->handleRequest($request);

        $costs = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $type = $form->get('type')->getData();
            $method = $form->get('method')->getData();
            $muc_dau_tu = $form->get('muc_dau_tu')->getData();
            $long = $form->get('long')->getData();
            $wide = $form->get('wide')->getData();
            $floor = $form->get('floor')->getData() ? $form->get('floor')->getData() : 1;
            
            $mong = $form->get('mong')->getData();
            $mai = $form->get('mai')->getData();
            
            $mat_tien = $form->get('mat_tien')->getData();
            $hem = $form->get('hem')->getData();
            $lung = $form->get('lung')->getData();
            $tum = $form->get('tum')->getData();
            $san_thuong = $form->get('san_thuong')->getData();
            $ban_cong = $form->get('ban_cong')->getData();
            $tang_ham = $form->get('tang_ham')->getData();
            $san_vuon = $form->get('san_vuon')->getData();

            $cost = 0;
            $title = '';
            
            // Validate numeric
            if (!is_numeric($long) || !is_numeric($wide) || !is_numeric($type) || !is_numeric($method) || !is_numeric($floor)) {
                $this->addFlash('error', "Vui lòng nhập đúng dữ liệu");
                return $this->redirectToRoute('caculator_cost_construction');
            }

            $area = $long * $wide;

            // 1. Get Base Cost
            if ($type === 1) { // Nhà phố
                if ($method === 1) { 
                    $cost = $settingsManager->get('cost_nha_pho_phan_tho', 3200000); 
                    $title = "Đơn giá xây nhà phần thô nhà phố"; 
                } else { 
                    $cost = $settingsManager->get('cost_nha_pho_tron_goi', 5000000); 
                    $title = "Đơn giá xây nhà trọn gói nhà phố"; 
                }
            } elseif ($type === 3) { // Nhà cấp 4
                if ($method === 1) { 
                    $cost = $settingsManager->get('cost_nha_cap4_phan_tho', 3000000); 
                    $title = "Đơn giá xây nhà cấp 4 phần thô"; 
                } else { 
                    $cost = $settingsManager->get('cost_nha_cap4_tron_goi', 4900000); 
                    $title = "Đơn giá xây nhà cấp 4 trọn gói"; 
                }
            } else { // Biệt thự
                if ($method === 1) { 
                    $cost = $settingsManager->get('cost_biet_thu_phan_tho', 3600000); 
                    $title = "Đơn giá xây dựng biệt thự phần thô"; 
                } else { 
                    $cost = $settingsManager->get('cost_biet_thu_tron_goi', 6000000); 
                    $title = "Đơn giá xây dựng biệt thự trọn gói"; 
                }
            }

            // 2. Surcharge for small area
            $threshold = $settingsManager->get('cost_small_area_threshold', 60);
            $smallAreaSurcharge = $settingsManager->get('cost_small_area_surcharge', 10);
            if ($area < $threshold) {
                $cost = $cost * (1 + ($smallAreaSurcharge / 100));
            }

            // 3. Investment Level Ratio
            if ($muc_dau_tu === 2) {
                $cost = $cost * $settingsManager->get('cost_level_tb_kha_ratio', 1.15);
            } elseif ($muc_dau_tu === 3) {
                $cost = $cost * $settingsManager->get('cost_level_kha_plus_ratio', 1.3);
            }

            // 4. Calculate Areas
            $areaMong = 0; $titleMong = "Không có móng";
            $areaMai = 0; $titleMai = "Không có mái";
            $areaLung = 0; $areaTum = 0; $areaSanThuong = 0; 
            $areaBanCong = 0; $areaTangHam = 0; $areaSanVuon = 0;

            if ($type !== 3) {
                // Mong
                if ($mong === 1) {
                    $areaMong = $area * $settingsManager->get('cost_mong_coc_ratio', 0.5);
                    $titleMong = "Móng cọc (Móng đài)";
                } elseif ($mong === 2) {
                    $areaMong = $area * $settingsManager->get('cost_mong_bang_ratio', 0.55);
                    $titleMong = "Móng băng";
                } else {
                    $areaMong = $area * $settingsManager->get('cost_mong_don_ratio', 0.3);
                    $titleMong = "Móng đơn";
                }

                // Mai
                if ($mai === 1) {
                    $areaMai = $area * $settingsManager->get('cost_mai_btct_ratio', 0.7);
                    $titleMai = "Mái BTCT đúc bằng";
                } elseif ($mai === 2) {
                    $areaMai = $area * $settingsManager->get('cost_mai_ton_ratio', 0.3);
                    $titleMai = "Mái lợp tôn lạnh";
                } elseif ($mai === 3) {
                    $areaMai = $area * $settingsManager->get('cost_mai_ngoi_xago_ratio', 0.7);
                    $titleMai = "Mái xà gồ thép lợp ngói";
                } else {
                    $areaMai = $area * $settingsManager->get('cost_mai_ngoi_btct_ratio', 1.0);
                    $titleMai = "Mái BTCT lợp ngói";
                }
                
                // Lung, Tum, San thuong
                if ($lung === 1) $areaLung = $area * $settingsManager->get('cost_lung_ratio', 1.0);
                if ($tum === 1) $areaTum = $area * $settingsManager->get('cost_tum_ratio', 0.7);
                if ($san_thuong === 1) {
                    $areaSanThuong = $area * $settingsManager->get('cost_san_thuong_ratio', 0.3);
                } elseif ($san_thuong === 2) {
                    $areaSanThuong = $area * $settingsManager->get('cost_san_thuong_mai_ratio', 0.5);
                }
                
                // Ban cong
                if ($ban_cong === 1) $areaBanCong = $floor * $settingsManager->get('cost_ban_cong_area', 4.0);
                
                // Tang ham
                if ($tang_ham === 1) $areaTangHam = $area * $settingsManager->get('cost_ham_1_0_1_2_ratio', 1.5);
                elseif ($tang_ham === 2) $areaTangHam = $area * $settingsManager->get('cost_ham_1_2_1_5_ratio', 1.7);
                elseif ($tang_ham === 3) $areaTangHam = $area * $settingsManager->get('cost_ham_1_5_1_7_ratio', 2.0);
                elseif ($tang_ham === 4) $areaTangHam = $area * $settingsManager->get('cost_ham_1_7_2_0_ratio', 2.3);
                elseif ($tang_ham === 5) $areaTangHam = $area * $settingsManager->get('cost_ham_2_0_2_5_ratio', 2.5);
                elseif ($tang_ham === 6) $areaTangHam = $area * $settingsManager->get('cost_ham_2_5_3_0_ratio', 3.0);
                
                // San vuon
                if ($san_vuon === 1) $areaSanVuon = $area * $settingsManager->get('cost_san_vuon_ratio', 0.3);

                $areaTotal = ($area * $floor) + $areaMong + $areaMai + $areaLung + $areaTum + $areaSanThuong + $areaBanCong + $areaTangHam + $areaSanVuon;
            } else {
                $areaTotal = $area; // Nhà cấp 4
            }

            // 5. Total Cost
            $costTotal = $cost * $areaTotal;
            
            // 6. Final Surcharges (Hem, Mat Tien)
            $surcharge = 0;
            if ($hem === 2) $surcharge += $settingsManager->get('cost_hem_3_5m_surcharge', 3);
            elseif ($hem === 3) $surcharge += $settingsManager->get('cost_hem_nho_3m_surcharge', 5);
            
            if ($mat_tien === 2) $surcharge += $settingsManager->get('cost_2_mat_tien_surcharge', 5);
            
            if ($surcharge > 0) {
                $costTotal = $costTotal * (1 + ($surcharge / 100));
            }
            
            $note = 'Chi phí xây dựng trên chỉ mang tính chất tham khảo.';
            if ($area < $threshold) {
                $note .= ' Giá đã bao gồm phụ phí ' . $smallAreaSurcharge . '% cho diện tích nhỏ (< ' . $threshold . 'm²).';
            }
            if ($surcharge > 0) {
                $note .= ' Giá đã bao gồm phụ phí thi công/mặt tiền (' . $surcharge . '%).';
            }

            $costs = (object) array(
                'area' => $area,
                'floor' => $floor,
                'titleMong' => $titleMong,
                'areaMong' => $areaMong,
                'titleMai' => $titleMai,
                'areaMai' => $areaMai,
                'areaLung' => $areaLung,
                'areaTum' => $areaTum,
                'areaSanThuong' => $areaSanThuong,
                'areaBanCong' => $areaBanCong,
                'areaTangHam' => $areaTangHam,
                'areaSanVuon' => $areaSanVuon,
                'areaTotal' => $areaTotal,
                'cost' => $cost, // Final adjusted unit price
                'costTotal' => $costTotal,
                'title' => $title,
                'note' => $note
            );
        }

        $breadcrumbs = $this->get("white_october_breadcrumbs");
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        $breadcrumbs->addItem('Dự toán chi phí xây dựng');

        $post = $this->getDoctrine()
            ->getRepository(News::class)
            ->findOneBy(
                array('url' => 'chi-phi-xay-dung')
            );

        $contentsLazy = $post ? $this->contentFormatter->lazyloadContent($post) : '';

        if (!empty($type) && $type === 'page') {
            return $this->render('form/caculatorcost/page.html.twig', [
                'form' => $form->createView()
            ]);
        } elseif (!empty($type) && $type === 'sidebar') {
            return $this->render('form/caculatorcost/sidebar.html.twig', [
                'form' => $form->createView()
            ]);
        } else {
            return $this->render('form/caculatorcost/caculator.html.twig', [
                'form' => $form->createView(),
                'costs' => $costs ? $costs : null,
                'post' => $post,
                'contentsLazy' => $contentsLazy
            ]);
        }
    }

}


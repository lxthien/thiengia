<?php

namespace App\Form;

use App\Entity\News;
use App\Enum\PostStatus;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Service\PageBuilderService;

class PageType extends AbstractType
{
    private $authorizationChecker;
    private $doctrine;
    private $pageBuilderService;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        ManagerRegistry $doctrine,
        PageBuilderService $pageBuilderService
    )
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->doctrine = $doctrine;
        $this->pageBuilderService = $pageBuilderService;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addParentField($builder);

        $builder
            ->add('title', null, [
                'attr' => ['class' => 'sluggable'],
                'label' => 'label.title',
            ])
            ->add('url', TextType::class, [
                'attr' => ['class' => 'url', 'readonly' => 'readonly'],
                'label' => 'label.url',
            ])
            ->add('status', EnumType::class, [
                'class' => PostStatus::class,
                'choice_label' => fn (PostStatus $status) => $status->label(),
                'label' => 'Trạng thái',
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'label' => 'Ngày đặt lịch',
                'attr' => ['class' => 'js-scheduled-at'],
            ])
            // Ảnh được chọn qua Media Library picker, ghi thẳng vào field này
            // (xem admin/page/_form.html.twig) — không còn qua Vich.
            ->add('images', HiddenType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'label.description',
            ])
            ->add('pageBuilderEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Bật Page Builder',
            ])
            ->add('pageBuilderData', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'page-builder-data-input',
                ],
            ])
            ->add('contents', TextareaType::class, [
                'required' => false,
                'attr' => ['class' => 'txt-ckeditor', 'data-height' => '500'],
                'label' => 'label.contents',
            ])
            ->add('pageTitle', TextType::class, [
                'required' => false,
                'label' => 'label.pageTitle',
            ])
            ->add('pageDescription', TextareaType::class, [
                'required' => false,
                'label' => 'label.pageDescription',
            ])
            ->add('pageKeyword', TextType::class, [
                'required' => false,
                'label' => 'label.pageKeyword',
            ])
            ->add('robots', TextType::class, [
                'required' => false,
                'label' => 'Robots',
            ])
            ->add('postType', ChoiceType::class, [
                'required' => false,
                'label' => 'Type',
                'choices' => [
                    'Post' => 'post',
                    'Page' => 'page',
                ],
                'placeholder' => false,
                'empty_data' => 'page',
                'attr' => ['class' => 'postType-select'],
            ])
            ->add('breadcrumbTitle', TextType::class, [
                'required' => false,
                'label' => 'Breadcrumb Title (Short label for breadcrumbs)',
            ])
            ->add('schemaMarkup', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => '10'],
                'label' => 'Schema Markup',
            ])
            ->add('template', ChoiceType::class, [
                'required' => false,
                'label' => 'Template',
                'choices' => ['Mặc định' => '2_columns', 'Landing page' => '1_column', 'Dịch vụ' => 'service_page'],
                'empty_data' => '2_column',
                'placeholder' => false
            ])
            ->add('note', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => '3'],
                'label' => 'Ghi chú (Ví dụ: [ignore-outdated:all] hoặc [ignore-outdated:2018,2019])',
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $form = $event->getForm();
                $page = $event->getData();

                $this->addParentField($form, $page instanceof News ? $page : null);
                
                // Remove postType field for non-admin users
                if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    $form->remove('postType');
                }
            })
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $data = $event->getData();

                if (!is_array($data)) {
                    return;
                }

                $builderEnabled = !empty($data['pageBuilderEnabled']);
                $builderData = isset($data['pageBuilderData']) ? $data['pageBuilderData'] : null;
                $contents = isset($data['contents']) ? trim((string) $data['contents']) : '';

                if ($builderEnabled && !empty($builderData) && $contents === '') {
                    $data['contents'] = $this->pageBuilderService->buildLegacyHtmlFromJson($builderData);
                    $event->setData($data);
                }
            });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => News::class,
        ]);
    }

    private function addParentField($form, News $currentPage = null)
    {
        $pages = $this->doctrine
            ->getRepository(News::class)
            ->findBy(['postType' => 'page'], ['title' => 'ASC']);

        $excludedIds = [];
        if ($currentPage && $currentPage->getId()) {
            $excludedIds = $this->collectDescendantIds($currentPage);
            $excludedIds[] = $currentPage->getId();
        }

        $pages = array_values(array_filter($pages, function (News $page) use ($excludedIds) {
            return !in_array($page->getId(), $excludedIds, true);
        }));

        $orderedPages = $this->buildHierarchicalPageList($pages);
        $labels = [];

        foreach ($orderedPages as $item) {
            $prefix = str_repeat('------ ', $item['level']);
            $labels[$item['page']->getId()] = $prefix . $item['page']->getTitle();
        }

        $form->add('parent', EntityType::class, [
            'class' => News::class,
            'choices' => array_map(function ($item) {
                return $item['page'];
            }, $orderedPages),
            'choice_label' => function (News $page) use ($labels) {
                return isset($labels[$page->getId()]) ? $labels[$page->getId()] : $page->getTitle();
            },
            'required' => false,
            'label' => 'label.parent_page',
            'placeholder' => 'Chọn trang cha (tùy chọn)',
        ]);
    }

    private function buildHierarchicalPageList(array $pages, $parentId = null, $level = 0)
    {
        $items = [];

        foreach ($pages as $page) {
            $pageParent = $page->getParent();
            $pageParentId = $pageParent ? $pageParent->getId() : null;

            if ($pageParentId !== $parentId) {
                continue;
            }

            $items[] = [
                'page' => $page,
                'level' => $level,
            ];

            $items = array_merge(
                $items,
                $this->buildHierarchicalPageList($pages, $page->getId(), $level + 1)
            );
        }

        return $items;
    }

    private function collectDescendantIds(News $page)
    {
        $ids = [];

        foreach ($page->getChildren() as $child) {
            if (!$child->getId()) {
                continue;
            }

            $ids[] = $child->getId();
            $ids = array_merge($ids, $this->collectDescendantIds($child));
        }

        return array_values(array_unique($ids));
    }
}

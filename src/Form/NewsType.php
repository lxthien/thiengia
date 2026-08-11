<?php

namespace App\Form;

use App\Entity\NewsCategory;
use App\Entity\News;
use App\Enum\PostStatus;
use App\Form\Type\TagsInputType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class NewsType extends AbstractType
{
    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
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
            // (xem admin/news/_form.html.twig) — không còn qua Vich.
            ->add('images', HiddenType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'label.description',
            ])
            ->add('contents', TextareaType::class, [
                'attr' => ['class' => 'txt-ckeditor', 'data-height' => '500'],
                'label' => 'label.contents',
            ])
            ->add('ordering', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'ordering'],
                'label' => 'label.ordering',
            ])
            ->add('categoryPrimary', HiddenType::class, [
                'required' => false
            ])
            ->add('category', EntityType::class, [
                'required' => false,
                'label' => 'label.category',
                'class' => \App\Entity\NewsCategory::class,
                'multiple' => true,
                'expanded' => true,
                'choice_attr' => function (NewsCategory $category) {
                    $parentId = $category->getParentcat() instanceof NewsCategory 
                        ? $category->getParentcat()->getId() 
                        : null;
                    
                    return [
                        'data-parent-id' => $parentId,
                        'data-category-id' => $category->getId(),
                        'class' => 'category-checkbox',
                    ];
                },
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
                'empty_data' => 'post',
                'attr' => ['class' => 'postType-select'],
            ])
            ->add('relatedNews', TextType::class, [
                'required' => false,
                'label' => 'label.relatedNews',
            ])
            ->add('contactHotline', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => '5'],
                'label' => 'Hotline',
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
                
                // Remove postType field for non-admin users
                if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                    $form->remove('postType');
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
}

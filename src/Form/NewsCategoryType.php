<?php

namespace App\Form;

use App\Entity\NewsCategory;
use App\Form\Type\DateTimePickerType;
use App\Form\Type\TagsInputType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewsCategoryType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('parentcat', null, [
                'attr' => ['autofocus' => true],
                'label' => 'label.parentcat',
            ])
            ->add('name', TextType::class, [
                'attr' => ['class' => 'sluggable'],
                'label' => 'label.name',
            ])
            ->add('url', TextType::class, [
                'attr' => ['class' => 'url', 'readonly' => 'readonly'],
                'label' => 'label.url',
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['class' => 'txt-ckeditor', 'data-height' => '200'],
                'label' => 'label.description',
            ])
            ->add('content', TextareaType::class, [
                'attr' => ['class' => 'txt-ckeditor', 'data-height' => '600'],
                'label' => 'Nội dung',
            ])
            ->add('showPostRelated', CheckboxType::class, [
                'required' => false,
                'label' => 'Hiển thị tin cùng danh mục',
            ])
            ->add('enable', CheckboxType::class, [
                'required' => false,
                'label' => 'label.enable',
            ])
            ->add('sortBy', ChoiceType::class, [
                'required' => false,
                'label' => 'label.sortBy',
                'choices' => ['label.default' => '{"createdAt":"desc"}', 'label.ordering' => '{"ordering":"asc"}'],
                'empty_data' => '"createdAt":"desc"}',
                'placeholder' => false
            ])
            ->add('cardFormat', ChoiceType::class, [
                'required' => false,
                'label' => 'Định dạng Card (Tỉ lệ ảnh)',
                'choices' => [
                    'Ảnh Thường (277x220)' => 'news_277_220',
                    'Ảnh Dọc (277x350)' => 'news_277_350'
                ],
                'empty_data' => 'news_277_220',
                'placeholder' => false
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
            ->add('schemaMarkup', TextareaType::class, [
                'attr' => ['rows' => '10'],
                'required' => false,
                'label' => 'Schema Markup',
            ])
            ->add('thumbnail', HiddenType::class, [
                'required' => false,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => NewsCategory::class,
        ]);
    }
}

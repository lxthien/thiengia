<?php

namespace App\Form;

use App\Entity\MenuItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuItemType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'attr' => ['class' => 'form-control'],
                'required' => true,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'label.type',
                'attr' => ['class' => 'form-control'],
                'choices' => [
                    'Custom URL' => MenuItem::TYPE_URL,
                    'News Article' => MenuItem::TYPE_NEWS,
                    'Category' => MenuItem::TYPE_CATEGORY,
                    'Page' => MenuItem::TYPE_PAGE,
                ],
                'required' => true,
            ])
            ->add('url', TextType::class, [
                'label' => 'label.url',
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://example.com or /path'],
                'required' => false,
            ])
            ->add('cssClass', TextType::class, [
                'label' => 'CSS Class',
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g., btn btn-primary'],
                'required' => false,
            ])
            ->add('targetAttr', ChoiceType::class, [
                'label' => 'Open In',
                'attr' => ['class' => 'form-control'],
                'choices' => [
                    'Same Window' => '_self',
                    'New Window' => '_blank',
                ],
                'required' => true,
            ])
            ->add('titleAttr', TextType::class, [
                'label' => 'Tooltip Title (SEO)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Hover tooltip text'],
                'required' => false,
            ])
            ->add('enable', CheckboxType::class, [
                'label' => 'label.enable',
                'label_attr' => ['class' => 'checkbox-label'],
                'required' => false,
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MenuItem::class,
        ]);
    }
}

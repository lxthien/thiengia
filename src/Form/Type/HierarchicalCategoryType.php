<?php

namespace App\Form\Type;

use App\Entity\NewsCategory;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HierarchicalCategoryType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Build the form with EntityType to preserve data binding
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        
        // Get all categories
        $allCategories = $options['all_categories'];
        
        // Build hierarchical tree
        $rootCategories = array_filter($allCategories, function(NewsCategory $cat) {
            return $cat->getParentcat() === 'root' || $cat->getParentcat() === null;
        });
        
        $view->vars['hierarchical_categories'] = $rootCategories;
        $view->vars['all_categories'] = $allCategories;
        $view->vars['block_name'] = 'hierarchical_category';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => 'label.category',
            'all_categories' => [],
            'class' => NewsCategory::class,
            'multiple' => true,
            'expanded' => true,
            'compound' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'hierarchical_category_type';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return EntityType::class;
    }
}

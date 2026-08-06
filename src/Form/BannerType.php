<?php

namespace App\Form;

use App\Entity\Banner;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Vich\UploaderBundle\Form\Type\VichFileType;

class BannerType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('bannercategory', null, [
                'label' => 'label.category',
            ])
            ->add('name', TextType::class, [
                'label' => 'label.name',
            ])
            ->add('url', TextType::class, [
                'required' => false,
                'label' => 'label.url',
            ])
            ->add('imageFile', VichFileType::class, [
                'allow_delete' => true,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Banner::class,
        ]);
    }
}

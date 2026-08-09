<?php

namespace App\Form;

use App\Entity\GalleryAlbum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class GalleryAlbumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Tên hoạt động',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Mô tả',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('enable', CheckboxType::class, [
                'required' => false,
                'label' => 'Hiển thị',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => GalleryAlbum::class,
        ]);
    }
}

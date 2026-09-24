<?php

namespace App\Form\Homepage;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Một gạch đầu dòng trong khối giới thiệu: "<b>Tiêu đề:</b> nội dung".
 */
class AboutCheckItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Phần in đậm',
                'constraints' => [new NotBlank(message: 'Nhập phần in đậm.'), new Length(max: 80)],
            ])
            ->add('text', TextType::class, [
                'label' => 'Phần còn lại',
                'constraints' => [new NotBlank(message: 'Nhập nội dung.'), new Length(max: 300)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}

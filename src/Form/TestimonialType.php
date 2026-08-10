<?php

namespace App\Form;

use App\Entity\Testimonial;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class TestimonialType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Tên khách hàng',
            ])
            ->add('role', TextType::class, [
                'required' => false,
                'label' => 'Mô tả ngắn',
            ])
            ->add('text', TextareaType::class, [
                'label' => 'Nội dung đánh giá',
                'attr' => ['rows' => 5],
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'Số sao',
                'choices' => [
                    '5 sao' => 5,
                    '4 sao' => 4,
                    '3 sao' => 3,
                    '2 sao' => 2,
                    '1 sao' => 1,
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            // Ảnh được chọn qua Media Library picker, ghi thẳng vào field này (xem admin/testimonial/_form_media.html.twig)
            ->add('avatarUrl', HiddenType::class)
            ->add('enable', CheckboxType::class, [
                'required' => false,
                'label' => 'Hiển thị',
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Testimonial::class,
        ]);
    }
}

<?php

namespace App\Form\Homepage;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Một con số trong dải số liệu trang chủ, ví dụ: 500 + "Công trình bàn giao".
 * Lưu vào HomepageSection::config['items'].
 */
class FigureItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('value', IntegerType::class, [
                'label' => 'Con số',
                'constraints' => [new NotBlank(message: 'Nhập con số.'), new Range(min: 0, max: 100000)],
                'help' => 'Số chạy từ 0 lên khi người xem cuộn tới.',
            ])
            ->add('unit', TextType::class, [
                'label' => 'Ký hiệu sau số',
                'required' => false,
                'constraints' => [new Length(max: 8)],
                'help' => 'Ví dụ: + hoặc %',
            ])
            ->add('label', TextType::class, [
                'label' => 'Chú thích',
                'constraints' => [new NotBlank(message: 'Nhập chú thích.'), new Length(max: 80)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}

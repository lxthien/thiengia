<?php

namespace App\Form\Homepage;

use App\Service\Homepage\SectionIcons;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Một cam kết trên trang chủ. Lưu vào HomepageSection::config['items'].
 *
 * Icon là danh sách chọn chứ không phải ô nhập: template in icon bằng |raw
 * nên markup phải nằm trong code (xem SectionIcons).
 */
class CommitmentItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Tên cam kết',
                'constraints' => [new NotBlank(message: 'Nhập tên cam kết.'), new Length(max: 80)],
            ])
            ->add('desc', TextareaType::class, [
                'label' => 'Diễn giải',
                'attr' => ['rows' => 2],
                'constraints' => [new NotBlank(message: 'Nhập diễn giải.'), new Length(max: 300)],
            ])
            ->add('icon', ChoiceType::class, [
                'label' => 'Icon',
                'choices' => SectionIcons::choices(),
                'placeholder' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}

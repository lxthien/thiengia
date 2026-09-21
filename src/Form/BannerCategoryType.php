<?php

namespace App\Form;

use App\Entity\BannerCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

class BannerCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Tên nhóm', 'constraints' => [new Assert\Length(max: 255)],
            ])
            ->add('url', TextType::class, [
                'label' => 'Mã nhóm', 'required' => false,
                'help' => 'Tự tạo từ tên nếu để trống. Chỉ dùng chữ thường, số và dấu gạch ngang.',
                'constraints' => [new Assert\Length(max: 255), new Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Mã nhóm chỉ gồm chữ thường, số và dấu gạch ngang.')],
            ])
            ->add('zone', ChoiceType::class, [
                'label' => 'Vị trí hiển thị', 'choices' => array_flip(BannerCategory::ZONES),
                'help' => 'Các banner đang bật trong cùng vị trí sẽ được hiển thị cùng nhau.',
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
                $data = $event->getData();
                if (is_array($data) && is_string($data['name'] ?? null) && is_string($data['url'] ?? '') && trim($data['url'] ?? '') === '') {
                    $data['url'] = (string) (new AsciiSlugger('vi'))->slug($data['name'])->lower();
                    $event->setData($data);
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BannerCategory::class]);
    }
}

<?php

namespace App\Form;

use App\Entity\GalleryAlbum;
use App\Entity\Project;
use App\Repository\GalleryAlbumRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Tên công trình',
                'help' => 'Ví dụ: Biệt thự anh Minh — Thảo Điền',
            ])
            ->add('spec', TextType::class, [
                'label' => 'Dòng thông số',
                'help' => 'Hiển thị dưới tên trên trang chủ. Ví dụ: Đất 10×20m · 3 tầng · Trọn gói 850 m² sàn',
            ])
            // HiddenType: giá trị do Media Picker ghi vào, xem _image_picker.html.twig.
            ->add('coverImage', HiddenType::class, [
                'required' => false,
                'label' => 'Ảnh đại diện',
            ])
            ->add('linkUrl', TextType::class, [
                'required' => false,
                'label' => 'Link khi bấm vào',
                'help' => 'Dán đường dẫn bài viết về công trình này. Để trống thì thẻ không dẫn đi đâu.',
            ])
            ->add('album', EntityType::class, [
                'required' => false,
                'label' => 'Album ảnh chi tiết',
                'class' => GalleryAlbum::class,
                'choice_label' => 'name',
                'placeholder' => 'Không gắn album',
                'query_builder' => static fn (GalleryAlbumRepository $repository) => $repository
                    ->createQueryBuilder('a')->orderBy('a.position', 'ASC')->addOrderBy('a.id', 'ASC'),
                'help' => 'Để dành cho trang chi tiết công trình sau này.',
            ])
            ->add('area', TextType::class, ['required' => false, 'label' => 'Diện tích'])
            ->add('floors', TextType::class, ['required' => false, 'label' => 'Số tầng'])
            ->add('location', TextType::class, ['required' => false, 'label' => 'Địa điểm'])
            ->add('slug', TextType::class, [
                'required' => false,
                'label' => 'Slug',
                'help' => 'Để dành cho trang chi tiết sau này. Chưa dùng tới, có thể để trống.',
            ])
            ->add('metaTitle', TextType::class, ['required' => false, 'label' => 'Meta title'])
            ->add('metaDescription', TextareaType::class, [
                'required' => false,
                'label' => 'Meta description',
                'attr' => ['rows' => 2],
            ])
            ->add('featuredOnHome', CheckboxType::class, [
                'required' => false,
                'label' => 'Đưa lên khối "Công trình thực tế" ở trang chủ',
            ])
            ->add('enable', CheckboxType::class, [
                'required' => false,
                'label' => 'Hiển thị công trình này',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Project::class]);
    }
}

<?php

namespace App\Form;

use App\Entity\HomepageSection;
use App\Entity\NewsCategory;
use App\Enum\SectionType;
use App\Form\Homepage\AboutCheckItemType;
use App\Form\Homepage\CommitmentItemType;
use App\Form\Homepage\FigureItemType;
use App\Form\Homepage\StepItemType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Form sửa 1 khối trang chủ.
 *
 * Mỗi loại khối hiện đúng những ô nó thật sự dùng: phần chữ theo
 * SectionType::editableFields, phần nội dung lặp theo CONFIG_FIELDS.
 *
 * Các ô nội dung đều `mapped => false` và được gom vào HomepageSection::config
 * ở POST_SUBMIT, nhờ vậy controller không phải biết từng loại khối có ô gì.
 */
class HomepageSectionType extends AbstractType
{
    private const EMPTY_HELP = 'Để trống thì trang chủ dùng nội dung mặc định của theme.';

    /**
     * Loại khối (SectionType::value) => các khóa trong `config` mà form này
     * quản lý. Dùng chuỗi vì PHP 8.1 chưa cho đọc ->value của enum trong hằng số.
     */
    private const CONFIG_FIELDS = [
        'hero' => ['points'],
        'figures' => ['items'],
        'commitments' => ['items'],
        'about' => ['image', 'imageAlt', 'checks'],
        'steps' => ['items'],
        'news' => ['limit', 'categories'],
        'cta' => ['image'],
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $section = $event->getData();

            if ($section instanceof HomepageSection) {
                $this->addFields($event->getForm(), $section);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $section = $event->getData();

            if ($section instanceof HomepageSection) {
                $this->writeConfig($event->getForm(), $section);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => HomepageSection::class]);
    }

    /**
     * @return list<string>
     */
    public static function configKeys(SectionType $type): array
    {
        return self::CONFIG_FIELDS[$type->value] ?? [];
    }

    private function addFields(FormInterface $form, HomepageSection $section): void
    {
        $type = $section->getType();

        if ($type->hasField('label')) {
            $form->add('label', TextType::class, [
                'required' => false,
                'label' => 'Chữ nhỏ phía trên tiêu đề',
                'help' => self::EMPTY_HELP,
            ]);
        }

        if ($type->hasField('title')) {
            $form->add('title', TextType::class, [
                'required' => false,
                'label' => 'Tiêu đề',
                'help' => self::EMPTY_HELP . ' Dùng thẻ <em> để in nghiêng phần muốn nhấn mạnh, ví dụ: 6 cam kết <em>bằng văn bản</em>.',
            ]);
        }

        if ($type->hasField('subtitle')) {
            $form->add('subtitle', TextareaType::class, [
                'required' => false,
                'label' => $type->subtitleLabel(),
                'attr' => ['rows' => 4],
                'help' => self::EMPTY_HELP,
            ]);
        }

        match ($type) {
            SectionType::Hero => $this->addCollection($form, $section, 'points', TextType::class, 'Gạch đầu dòng dưới đoạn giới thiệu', [
                'label' => false,
                'constraints' => [new NotBlank(message: 'Nhập nội dung hoặc xóa dòng này.'), new Length(max: 160)],
            ]),
            SectionType::Figures => $this->addCollection($form, $section, 'items', FigureItemType::class, 'Các con số'),
            SectionType::Commitments => $this->addCollection($form, $section, 'items', CommitmentItemType::class, 'Danh sách cam kết'),
            SectionType::Steps => $this->addCollection($form, $section, 'items', StepItemType::class, 'Các bước'),
            SectionType::About => $this->addAboutFields($form, $section),
            SectionType::Cta => $this->addImageField($form, $section, 'Ảnh nền khối CTA'),
            SectionType::News => $this->addNewsFields($form, $section),
            default => null,
        };

        $form->add('enable', CheckboxType::class, [
            'required' => false,
            'label' => 'Hiển thị khối này trên trang chủ',
        ]);
    }

    private function addNewsFields(FormInterface $form, HomepageSection $section): void
    {
        $form->add('limit', IntegerType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'Số bài viết hiển thị',
            'data' => (int) $section->getConfigValue('limit', 4),
            'constraints' => [new Range(min: 1, max: 12)],
            'help' => 'Bố cục hiển thị đẹp nhất với 4 bài.',
        ]);

        $choices = [];

        foreach ($this->em->getRepository(NewsCategory::class)->findBy([], ['name' => 'ASC']) as $category) {
            $choices[(string) $category->getName()] = $category->getId();
        }

        $selected = array_values(array_filter(
            array_map('intval', (array) $section->getConfigValue('categories', [])),
            static fn (int $id): bool => in_array($id, $choices, true),
        ));

        $form->add('categories', ChoiceType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'Lấy bài từ danh mục',
            'choices' => $choices,
            'data' => $selected,
            'multiple' => true,
            'expanded' => true,
            'help' => 'Bỏ trống thì dùng cấu hình cũ ở Cài đặt chung ("Danh mục hiển thị trang chủ").',
        ]);
    }

    private function addAboutFields(FormInterface $form, HomepageSection $section): void
    {
        $this->addImageField($form, $section, 'Ảnh khối giới thiệu');

        $form->add('imageAlt', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'Mô tả ảnh (alt)',
            'data' => (string) $section->getConfigValue('imageAlt', ''),
            'constraints' => [new Length(max: 255)],
            'help' => 'Dùng cho SEO và người dùng trình đọc màn hình.',
        ]);

        $this->addCollection($form, $section, 'checks', AboutCheckItemType::class, 'Gạch đầu dòng');
    }

    private function addImageField(FormInterface $form, HomepageSection $section, string $label): void
    {
        // HiddenType: giá trị do Media Picker ghi vào (xem _image_picker.html.twig),
        // vẫn đi qua form nên giữ được giá trị khi submit lỗi validate.
        $form->add('image', HiddenType::class, [
            'mapped' => false,
            'required' => false,
            'label' => $label,
            'data' => (string) $section->getConfigValue('image', ''),
            'constraints' => [new Length(max: 255)],
        ]);
    }

    private function addCollection(
        FormInterface $form,
        HomepageSection $section,
        string $key,
        string $entryType,
        string $label,
        array $entryOptions = [],
    ): void {
        $simple = $entryType === TextType::class;

        $form->add($key, CollectionType::class, [
            'mapped' => false,
            'required' => false,
            'label' => $label,
            'entry_type' => $entryType,
            'entry_options' => $entryOptions + ['label' => false],
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => $simple,
            'prototype' => true,
            'by_reference' => false,
            'data' => $this->rows($section, $key, $simple),
            'help' => self::EMPTY_HELP,
        ]);
    }

    /**
     * Dữ liệu ban đầu của collection, đã lọc rác để form không vỡ khi config
     * bị sửa tay trong DB.
     *
     * @return list<mixed>
     */
    private function rows(HomepageSection $section, string $key, bool $simple): array
    {
        $rows = $section->getConfigValue($key, []);

        if (!is_array($rows)) {
            return [];
        }

        $rows = array_filter($rows, static fn ($row): bool => $simple ? is_string($row) : is_array($row));

        return array_values($rows);
    }

    private function writeConfig(FormInterface $form, HomepageSection $section): void
    {
        $keys = self::configKeys($section->getType());

        if ($keys === []) {
            return;
        }

        $config = $section->getConfig();

        foreach ($keys as $key) {
            if (!$form->has($key)) {
                continue;
            }

            $value = self::normalize($form->get($key)->getData());

            if ($value === null) {
                unset($config[$key]);
            } else {
                $config[$key] = $value;
            }
        }

        $section->setConfig($config);
    }

    /**
     * Trả về null cho "không có gì để lưu" — khóa đó bị bỏ khỏi config để
     * template quay về dùng nội dung mặc định của theme.
     */
    private static function normalize(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_array($value)) {
            $rows = [];

            foreach ($value as $row) {
                $row = is_array($row) ? array_filter($row, static fn ($cell): bool => $cell !== null && $cell !== '') : $row;

                if ($row === [] || $row === null || $row === '') {
                    continue;
                }

                $rows[] = $row;
            }

            return $rows === [] ? null : $rows;
        }

        return $value;
    }
}

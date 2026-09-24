<?php

namespace App\Form;

use App\Entity\Contact;
use App\Repository\ServiceRepository;
use App\Service\V3Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form "báo giá nhanh" ở khối hero trang chủ — gửi AJAX tới contact_ajax
 * (xem public/assets/js/v3/modules/forms.js).
 *
 * Danh sách nhu cầu lấy từ entity Service để luôn khớp với khối "Dịch vụ";
 * trước đây danh sách này bị gõ lại trong HomepageController và hai chỗ phải
 * tự nhớ đồng bộ. Chưa có dịch vụ nào trong DB thì lùi về cấu hình theme.
 */
class QuickQuoteType extends AbstractType
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly V3Config $config,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = $this->choices();

        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'Nguyễn Văn A', 'autocomplete' => 'name'],
            ])
            ->add('phone', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => '09xx xxx xxx', 'autocomplete' => 'tel'],
            ])
            ->add('title', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => array_combine($choices, $choices),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contact::class]);
    }

    /**
     * @return list<string>
     */
    private function choices(): array
    {
        $names = $this->serviceRepository->findActiveNames();

        if ($names === []) {
            $names = (array) $this->config->get('menu.services', []);
        }

        return array_values(array_filter(
            $names,
            static fn ($name): bool => is_string($name) && trim($name) !== '',
        ));
    }
}

<?php

namespace App\Service\Homepage;

use App\Entity\BannerCategory;
use App\Entity\Contact;
use App\Entity\HomepageSection;
use App\Enum\SectionType;
use App\Form\QuickQuoteType;
use App\Repository\BannerRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Khối hero: ảnh nền lấy từ Banner nhóm "hero" + form báo giá nhanh.
 */
final class HeroSectionResolver implements SectionResolverInterface
{
    /**
     * Tên form bị ghim là 'form' để các input vẫn là form[name], form[phone]...
     * ContactController::ajaxAction dựng form không tên bằng createFormBuilder()
     * nên chờ đúng các key đó. Đổi tên ở đây là hỏng luồng gửi báo giá.
     */
    private const FORM_NAME = 'form';

    public function __construct(
        private readonly BannerRepository $bannerRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(SectionType $type): bool
    {
        return $type === SectionType::Hero;
    }

    public function resolve(HomepageSection $section): array
    {
        $form = $this->formFactory->createNamed(self::FORM_NAME, QuickQuoteType::class, new Contact(), [
            'action' => $this->urlGenerator->generate('contact_ajax'),
            'method' => 'POST',
        ]);

        return [
            'heroBanners' => $this->bannerRepository->findActiveByZone(BannerCategory::ZONE_HERO),
            'form' => $form->createView(),
        ];
    }
}

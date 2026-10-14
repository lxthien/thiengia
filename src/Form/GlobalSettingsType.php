<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GlobalSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('siteName', TextType::class, ['required' => false, 'label' => 'Tên Website'])
            ->add('pageTitle', TextType::class, ['required' => false, 'label' => 'Tiêu đề trang'])
            ->add('pageDescription', TextareaType::class, ['required' => false, 'label' => 'Mô tả trang'])
            ->add('pageKeyword', TextType::class, ['required' => false, 'label' => 'Từ khóa trang'])
            ->add('topKeyword', TextareaType::class, ['required' => false, 'attr' => ['rows' => 10], 'label' => 'Từ khóa hàng đầu'])
            
            ->add('emailContact', EmailType::class, ['required' => false, 'label' => 'Email Liên hệ'])
            ->add('companyAddress', TextType::class, ['required' => false, 'label' => 'Địa chỉ công ty'])
            ->add('companyTax', TextType::class, ['required' => false, 'label' => 'Mã số thuế'])
            ->add('companyWorkingHours', TextType::class, ['required' => false, 'label' => 'Giờ làm việc'])
            ->add('hotLine1', TextType::class, ['required' => false, 'label' => 'Hotline 1'])
            ->add('hotLine2', TextType::class, ['required' => false, 'label' => 'Hotline 2'])
            
            ->add('emailSender', EmailType::class, ['required' => false, 'label' => 'Email gửi đi (From)'])

            ->add('marqueeHomepage', TextareaType::class, ['required' => false, 'attr' => ['rows' => 7], 'label' => 'Dòng chữ chạy trang chủ'])
            
            ->add('listPrices', TextType::class, ['required' => false, 'label' => 'Bảng giá (ID bài viết/danh mục)'])
            ->add('listCategoryOnHomepage', TextareaType::class, ['required' => false, 'attr' => ['rows' => 7], 'label' => 'Danh mục hiển thị trang chủ'])
            ->add('numberRecordOnPage', NumberType::class, ['required' => false, 'label' => 'Số bài viết mỗi trang'])
            
            ->add('facebookMessenger', TextareaType::class, ['required' => false, 'attr' => ['rows' => 10], 'label' => 'Code Facebook Messenger'])
            ->add('googleAnalytic', TextareaType::class, ['required' => false, 'attr' => ['rows' => 7], 'label' => 'Mã Google Analytics'])
            ->add('GTM', TextType::class, ['required' => false, 'label' => 'Google Tag Manager ID'])
            ->add('ga4MeasurementID', TextType::class, ['required' => false, 'label' => 'GA4 Measurement ID'])
            ->add('fbApp', TextType::class, ['required' => false, 'label' => 'Facebook App ID'])
            
            ->add('linkToFacebook', TextType::class, ['required' => false, 'label' => 'Link Facebook'])
            ->add('linkToTiktok', TextType::class, ['required' => false, 'label' => 'Link TikTok'])
            ->add('linkToZalo', TextType::class, ['required' => false, 'label' => 'Link Zalo'])
            ->add('linkToTwitter', TextType::class, ['required' => false, 'label' => 'Link Twitter'])
            ->add('linkToYoutube', TextType::class, ['required' => false, 'label' => 'Link Youtube'])
            ->add('linkToLinkedin', TextType::class, ['required' => false, 'label' => 'Link Linkedin'])
            ->add('linkToInstagram', TextType::class, ['required' => false, 'label' => 'Link Instagram'])
            
            // Content variables (Placeholders)
            ->add('content_ten_cong_ty', TextType::class, ['required' => false, 'label' => 'Tên công ty'])
            ->add('content_ten_day_du', TextType::class, ['required' => false, 'label' => 'Tên đầy đủ'])
            ->add('content_dia_chi', TextareaType::class, ['required' => false, 'attr' => ['rows' => 3], 'label' => 'Địa chỉ'])
            ->add('content_hotline_1', TextType::class, ['required' => false, 'label' => 'Hotline 1'])
            ->add('content_hotline_2', TextType::class, ['required' => false, 'label' => 'Hotline 2'])
            ->add('content_hotline_3', TextType::class, ['required' => false, 'label' => 'Hotline 3'])
            ->add('content_email', TextType::class, ['required' => false, 'label' => 'Email'])
            ->add('content_mst', TextType::class, ['required' => false, 'label' => 'Mã số thuế'])
            ->add('content_website', TextType::class, ['required' => false, 'label' => 'Website', 'help' => 'Để trống thì [website] lấy tên miền đang chạy (vd xaydungthiengia.com).'])

            // Structured data (JSON-LD) — dán nguyên thẻ <script type="application/ld+json">...</script>
            ->add('schemaOrganization', TextareaType::class, ['required' => false, 'attr' => ['rows' => 14], 'label' => 'Schema Organization'])
            ->add('schemaLocalBusiness', TextareaType::class, ['required' => false, 'attr' => ['rows' => 14], 'label' => 'Schema LocalBusiness'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // no data class, we pass an array
        ]);
    }
}

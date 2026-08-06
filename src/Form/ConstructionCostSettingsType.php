<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConstructionCostSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $numberAttr = ['class' => 'form-control', 'step' => 'any'];

        $builder
            // === ĐƠN GIÁ GỐC (VNĐ/m²) — Diện tích sàn >= 60m² ===
            ->add('cost_nha_pho_phan_tho', NumberType::class, [
                'required' => false,
                'label' => 'Nhà phố - Phần thô (VNĐ/m²)',
                'attr' => $numberAttr,
            ])
            ->add('cost_nha_pho_tron_goi', NumberType::class, [
                'required' => false,
                'label' => 'Nhà phố - Trọn gói (VNĐ/m²)',
                'attr' => $numberAttr,
            ])
            ->add('cost_biet_thu_phan_tho', NumberType::class, [
                'required' => false,
                'label' => 'Biệt thự - Phần thô (VNĐ/m²)',
                'attr' => $numberAttr,
            ])
            ->add('cost_biet_thu_tron_goi', NumberType::class, [
                'required' => false,
                'label' => 'Biệt thự - Trọn gói (VNĐ/m²)',
                'attr' => $numberAttr,
            ])
            ->add('cost_nha_cap4_phan_tho', NumberType::class, [
                'required' => false,
                'label' => 'Nhà cấp 4 - Phần thô (VNĐ/m²)',
                'attr' => $numberAttr,
            ])
            ->add('cost_nha_cap4_tron_goi', NumberType::class, [
                'required' => false,
                'label' => 'Nhà cấp 4 - Trọn gói (VNĐ/m²)',
                'attr' => $numberAttr,
            ])

            // === PHỤ PHÍ DIỆN TÍCH NHỎ ===
            ->add('cost_small_area_threshold', NumberType::class, [
                'required' => false,
                'label' => 'Ngưỡng diện tích nhỏ (m²)',
                'attr' => $numberAttr,
            ])
            ->add('cost_small_area_surcharge', NumberType::class, [
                'required' => false,
                'label' => 'Phụ phí diện tích nhỏ (%)',
                'attr' => $numberAttr,
            ])

            // === HỆ SỐ MỨC ĐẦU TƯ ===
            ->add('cost_level_tb_kha_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Hệ số TB - Khá',
                'attr' => $numberAttr,
            ])
            ->add('cost_level_kha_plus_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Hệ số Khá+',
                'attr' => $numberAttr,
            ])

            // === HỆ SỐ MÓNG ===
            ->add('cost_mong_coc_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Móng cọc (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_mong_bang_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Móng băng (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_mong_don_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Móng đơn (hệ số)',
                'attr' => $numberAttr,
            ])

            // === HỆ SỐ MÁI ===
            ->add('cost_mai_btct_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Mái BTCT đúc bằng (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_mai_ton_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Mái lợp tôn lạnh (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_mai_ngoi_xago_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Mái xà gồ + ngói (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_mai_ngoi_btct_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Mái BTCT + ngói (hệ số)',
                'attr' => $numberAttr,
            ])

            // === HỆ SỐ HẠNG MỤC BỔ SUNG ===
            ->add('cost_lung_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Lửng (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_tum_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Tum / Tầng thượng (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_san_thuong_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Sân thượng không mái (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_san_thuong_mai_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Sân thượng có mái (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_ban_cong_area', NumberType::class, [
                'required' => false,
                'label' => 'Diện tích ban công mặc định (m²/tầng)',
                'attr' => $numberAttr,
            ])
            ->add('cost_san_vuon_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Sân vườn (hệ số)',
                'attr' => $numberAttr,
            ])

            // === PHỤ PHÍ HẺM ===
            ->add('cost_hem_3_5m_surcharge', NumberType::class, [
                'required' => false,
                'label' => 'Phụ phí hẻm 3-5m (%)',
                'attr' => $numberAttr,
            ])
            ->add('cost_hem_nho_3m_surcharge', NumberType::class, [
                'required' => false,
                'label' => 'Phụ phí hẻm < 3m (%)',
                'attr' => $numberAttr,
            ])

            // === PHỤ PHÍ MẶT TIỀN ===
            ->add('cost_2_mat_tien_surcharge', NumberType::class, [
                'required' => false,
                'label' => 'Phụ phí 2 mặt tiền (%)',
                'attr' => $numberAttr,
            ])

            // === HỆ SỐ TẦNG HẦM ===
            ->add('cost_ham_1_0_1_2_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Tầng hầm sâu 1.0-1.2m (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_ham_1_2_1_5_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Tầng hầm sâu 1.2-1.5m (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_ham_1_5_1_7_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Tầng hầm sâu 1.5-1.7m (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_ham_1_7_2_0_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Tầng hầm sâu 1.7-2.0m (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_ham_2_0_2_5_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Tầng hầm sâu 2.0-2.5m (hệ số)',
                'attr' => $numberAttr,
            ])
            ->add('cost_ham_2_5_3_0_ratio', NumberType::class, [
                'required' => false,
                'label' => 'Tầng hầm sâu 2.5-3.0m (hệ số)',
                'attr' => $numberAttr,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // no data class, we pass an array
        ]);
    }
}

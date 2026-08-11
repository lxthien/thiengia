<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\NewsCategory;
use App\Entity\News;
use App\Entity\Contact;
use App\Entity\Banner;
use App\Entity\BannerCategory;
use App\Entity\GalleryAlbum;
use App\Entity\Testimonial;
use App\Enum\PostStatus;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;

class HomepageController extends AbstractController
{
    private $settingsManager;
    private $em;

    public function __construct(SettingsManager $settingsManager, EntityManagerInterface $em)
    {
        $this->settingsManager = $settingsManager;
        $this->em = $em;
    }

    public function indexAction(Request $request)
    {
        $listPrices = $this->settingsManager->get('listPrices');
        $listCategoriesOnHomepage = $this->settingsManager->get('listCategoryOnHomepage');
        $blockPricesOnHomepage = array();
        $blocksOnHomepage = array();

        if (!empty($listPrices)) {
            $listPricesArray = explode(',', $listPrices);
            if (is_array($listPricesArray) && count($listPricesArray) > 0) {
                for ($i = 0; $i < count($listPricesArray); $i++) {
                    $post = $this->em->getRepository(News::class)
                                ->find($listPricesArray[$i]);
                    if ($post) {
                        $blockPricesOnHomepage[] = $post;
                    }
                }
            }
        }

        if (!empty($listCategoriesOnHomepage)) {
            $listCategoriesOnHomepage = json_decode($listCategoriesOnHomepage, true);

            if (is_array($listCategoriesOnHomepage)) {
                for ($i = 0; $i < count($listCategoriesOnHomepage); $i++) {
                    $blockOnHomepage = [];
                    $category = $this->em->getRepository(NewsCategory::class)
                                    ->find($listCategoriesOnHomepage[$i]["id"]);

                    if ($category) {
                        $listSubIds = isset($listCategoriesOnHomepage[$i]["subId"]) ? explode(",", $listCategoriesOnHomepage[$i]["subId"]) : [];
                        $listSubTabs = [];

                        if (!empty($listSubIds)) {
                            for ($j = 0; $j < count($listSubIds); $j++) {
                                $subCat = $this->em->getRepository(NewsCategory::class)
                                        ->find($listSubIds[$j]);

                                $posts = [];
                                if ($subCat) {
                                    $posts = $this->em->getRepository(News::class)
                                        ->createQueryBuilder('n')
                                        ->leftJoin('n.category', 't')
                                        ->where('t.id =:subCat')
                                        ->andWhere('n.status = :status')
                                        ->setParameter('subCat', $subCat->getId())
                                        ->setParameter('status', PostStatus::Published)
                                        ->orderBy('n.createdAt', 'DESC')
                                        ->setMaxResults( $listCategoriesOnHomepage[$i]["items"] )
                                        ->getQuery()->getResult();
                                }

                                $listSubTabs[] = (object) array('subCategory' => $subCat, 'posts' => $posts);
                            }
                        } else {
                            $posts = $this->em->getRepository(News::class)
                                ->createQueryBuilder('n')
                                ->leftJoin('n.category', 't')
                                ->where('t.id =:subCat')
                                ->andWhere('n.status = :status')
                                ->setParameter('subCat', $category->getId())
                                ->setParameter('status', PostStatus::Published)
                                ->orderBy('n.createdAt', 'DESC')
                                ->setMaxResults( $listCategoriesOnHomepage[$i]["items"] )
                                ->getQuery()->getResult();
                        }
                    }

                    $blockOnHomepage = (object) array(
                        'category' => $category, 
                        'listSubTabs' => $listSubTabs, 
                        'posts' => $posts ?? [], 
                        'description' => $listCategoriesOnHomepage[$i]["description"] ?? '', 
                        'title' => $listCategoriesOnHomepage[$i]["title"] ?? ''
                    );
                    $blocksOnHomepage[] = $blockOnHomepage;
                }
            }
        }

        // Form "báo giá nhanh" trên hero — gửi AJAX tới contact_ajax (xem assets/js/v3/modules/forms.js)
        $contact = new Contact();
        $form = $this->createFormBuilder($contact)
            ->setAction($this->generateUrl('contact_ajax'))
            ->setMethod('POST')
            ->add('name', TextType::class, array(
                'label' => false,
                'attr' => array('placeholder' => 'Nguyễn Văn A', 'autocomplete' => 'name'),
            ))
            ->add('phone', TextType::class, array(
                'label' => false,
                'attr' => array('placeholder' => '09xx xxx xxx', 'autocomplete' => 'tel'),
            ))
            ->add('title', ChoiceType::class, array(
                'label' => false,
                'required' => false,
                // Giữ đồng bộ với v3.menu.services trong config/packages/v3.yaml
                'choices' => array_combine($v3Services = [
                    'Xây nhà trọn gói',
                    'Xây nhà phần thô',
                    'Xây biệt thự',
                    'Sửa nhà trọn gói',
                    'Thiết kế kiến trúc',
                ], $v3Services),
            ))
            ->getForm();

        $heroBanners = $this->em->getRepository(Banner::class)->findActiveByZone(BannerCategory::ZONE_HERO);
        $galleryAlbums = $this->em->getRepository(GalleryAlbum::class)->findActiveOrdered();
        $testimonials = $this->em->getRepository(Testimonial::class)->findActiveOrdered();

        return $this->render('homepage/index.html.twig', [
            'blocksOnHomepage' => $blocksOnHomepage,
            'blockPricesOnHomepage' => $blockPricesOnHomepage,
            'showSlide' => true,
            'form' => $form->createView(),
            'heroBanners' => $heroBanners,
            'galleryAlbums' => $galleryAlbums,
            'testimonials' => $testimonials,
        ]);
    }
}

<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\NewsCategory;
use App\Entity\News;
use App\Entity\Contact;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use App\Service\ActivityLogService;
use App\Service\SettingsManager;

class HomepageController extends AbstractController
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
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
                    $post = $this->getDoctrine()
                                ->getRepository(News::class)
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
                    $category = $this->getDoctrine()
                                    ->getRepository(NewsCategory::class)
                                    ->find($listCategoriesOnHomepage[$i]["id"]);

                    if ($category) {
                        $listSubIds = isset($listCategoriesOnHomepage[$i]["subId"]) ? explode(",", $listCategoriesOnHomepage[$i]["subId"]) : [];
                        $listSubTabs = [];

                        if (!empty($listSubIds)) {
                            for ($j = 0; $j < count($listSubIds); $j++) {
                                $subCat = $this->getDoctrine()
                                        ->getRepository(NewsCategory::class)
                                        ->find($listSubIds[$j]);

                                $posts = [];
                                if ($subCat) {
                                    $posts = $this->getDoctrine()
                                        ->getRepository(News::class)
                                        ->createQueryBuilder('n')
                                        ->leftJoin('n.category', 't')
                                        ->where('t.id =:subCat')
                                        ->andWhere('n.enable = :enable')
                                        ->setParameter('subCat', $subCat->getId())
                                        ->setParameter('enable', 1)
                                        ->orderBy('n.createdAt', 'DESC')
                                        ->setMaxResults( $listCategoriesOnHomepage[$i]["items"] )
                                        ->getQuery()->getResult();
                                }

                                $listSubTabs[] = (object) array('subCategory' => $subCat, 'posts' => $posts);
                            }
                        } else {
                            $posts = $this->getDoctrine()
                                ->getRepository(News::class)
                                ->createQueryBuilder('n')
                                ->leftJoin('n.category', 't')
                                ->where('t.id =:subCat')
                                ->andWhere('n.enable = :enable')
                                ->setParameter('subCat', $category->getId())
                                ->setParameter('enable', 1)
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

        $contact = new Contact();
        $form = $this->createFormBuilder($contact)
            ->setAction($this->generateUrl('contact_ajax'))
            ->add('name', TextType::class, array('label' => 'label.author', 'attr' => array('placeholder' => 'Họ và tên *')))
            ->add('phone', TextType::class, array('label' => 'label.phone', 'attr' => array('placeholder' => 'Số điện thoại *')))
            ->add('email', EmailType::class, array('label' => 'label.author_email', 'attr' => array('placeholder' => 'Email (không bắt buộc)'), 'required' => false))
            ->add('contents', TextareaType::class, array(
                'label' => 'label.content',
                'attr' => array('rows' => '4', 'placeholder' => 'Nội dung yêu cầu tư vấn *')
            ))
            ->add('gclid', HiddenType::class, array('required' => false))
            ->add('send', SubmitType::class, array('label' => 'Gửi yêu cầu', 'attr' => array('class' => 'btn btn-primary ka-btn')))
            ->getForm();

        return $this->render('homepage/index.html.twig', [
            'blocksOnHomepage' => $blocksOnHomepage,
            'blockPricesOnHomepage' => $blockPricesOnHomepage,
            'showSlide' => true,
            'form' => $form->createView()
        ]);
    }
}

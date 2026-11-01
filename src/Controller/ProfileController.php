<?php

namespace App\Controller;

use App\Entity\News;
use App\Entity\User;
use App\Enum\PostStatus;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Trang tác giả công khai: hồ sơ (chức danh, giới thiệu, chuyên môn, link hồ
 * sơ khác) + danh sách bài đã viết. Là nơi schema Person của bài viết trỏ về
 * (author.url) để Google gom bài theo tác giả khi đánh giá E-E-A-T.
 *
 * Chỉ hiện khi tác giả bật "Hiển thị thông tin tác giả" và có đường dẫn
 * (User::isPublicAuthor), còn lại 404. URL dùng authorSlug, không dùng
 * username, để không lộ tên đăng nhập.
 */
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    #[Route('/tac-gia/{slug}/', name: 'author', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function indexAction(Request $request, string $slug): Response
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['authorSlug' => $slug]);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('Tác giả không tồn tại.');
        }

        // Chưa công khai (chưa tích "Hiển thị thông tin tác giả" hoặc tài khoản bị khóa):
        // khách nhận 404, người quản trị vẫn xem trước được (noindex, có thông báo).
        $preview = !$user->isPublicAuthor();
        if ($preview && !$this->isGranted('ROLE_ADMIN') && $this->getUser() !== $user) {
            throw $this->createNotFoundException('Tác giả không tồn tại hoặc chưa bật hiển thị.');
        }

        $page = max(1, $request->query->getInt('page', 1));

        $query = $this->em->getRepository(News::class)
            ->createQueryBuilder('n')
            ->where('n.author = :author')
            ->andWhere('n.status = :status')
            ->andWhere('n.postType = :postType')
            ->setParameter('author', $user)
            ->setParameter('status', PostStatus::Published)
            ->setParameter('postType', 'post')
            ->orderBy('n.publishedAt', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC');

        $pagination = $this->paginator->paginate(
            $query,
            $page,
            $this->settingsManager->get('numberRecordOnPage') ?: 12
        );

        // Trang vượt quá số trang thật → 404, tránh Google index trang rỗng.
        if ($page > 1 && count($pagination) === 0) {
            throw $this->createNotFoundException();
        }

        $bioText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $user->getBiography())), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $response = $this->render('user/author.html.twig', [
            'preview' => $preview,
            'author' => $user,
            'bioText' => $bioText,
            'bioSummary' => self::truncate($bioText, 300),
            'metaDescription' => self::truncate($bioText, 155),
            'baseUrl' => $this->generateUrl('author', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL),
            'pagination' => $pagination,
        ]);

        if ($preview) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->setPrivate();
        }

        return $response;
    }

    /** Cắt theo ranh giới từ, thêm "…" khi bị cắt. */
    private static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:-") . '…';
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig;

use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\NewsletterSubscriber;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * This Twig extension adds a new 'md2html' filter to easily transform Markdown
 * contents into HTML contents inside Twig templates.
 *
 * See https://symfony.com/doc/current/cookbook/templating/twig_extension.html
 *
 * In addition to creating the Twig extension class, before using it you must also
 * register it as a service. See app/config/services.yml file for details.
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Julien ITARD <julienitard@gmail.com>
 */
class AppExtension extends AbstractExtension
{
    /**
     * @var array
     */
    private $locales;

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    public function __construct(
        $locales,
        Registry $doctrine,
        AuthorizationCheckerInterface $authorizationChecker
    )
    {
        $this->locales = $locales;
        $this->doctrine = $doctrine;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('rating', [$this, 'renderRating'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('locales', [$this, 'getLocales']),
            new TwigFunction('admin_notifications', [$this, 'getAdminNotifications']),
        ];
    }

    /**
     * Transforms the given Markdown content into HTML content.
     *
     *  @param string $content
     *
     * @return string
     */
    public function markdownToHtml($content)
    {
        return $this->parser->toHtml($content);
    }

    /**
     * Renders a row of full/empty star divs for a numeric rating value.
     * Replaces blackknight467/star-rating-bundle's Twig filter (removed
     * because its own implementation relied on the PHP templating engine).
     */
    public function renderRating($number, $max = 5, $starSize = '')
    {
        $html = '<div class="rating">';
        for ($i = 1; $i <= $max; $i++) {
            $class = $i <= $number ? 'star-full' : 'star-empty';
            $html .= sprintf('<div class="%s %s"></div>', $class, $starSize);
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Takes the list of codes of the locales (languages) enabled in the
     * application and returns an array with the name of each locale written
     * in its own language (e.g. English, Français, Español, etc.).
     *
     * @return array
     */
    public function getLocales()
    {
        $localeCodes = explode('|', $this->locales);

        $locales = [];
        foreach ($localeCodes as $localeCode) {
            $locales[] = ['code' => $localeCode, 'name' => Intl::getLocaleBundle()->getLocaleName($localeCode, $localeCode)];
        }

        return $locales;
    }

    public function getAdminNotifications()
    {
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return [
                'total' => 0,
                'contacts' => ['count' => 0, 'items' => []],
                'comments' => ['count' => 0, 'items' => []],
                'users' => ['count' => 0, 'items' => []],
                'newsletter' => ['count' => 0, 'items' => []],
            ];
        }

        $contactRepository = $this->doctrine->getRepository(Contact::class);
        $commentRepository = $this->doctrine->getRepository(Comment::class);
        $userRepository = $this->doctrine->getRepository(User::class);
        $newsletterRepository = $this->doctrine->getRepository(NewsletterSubscriber::class);

        $contacts = $contactRepository->findUnreadNotifications();
        $comments = $commentRepository->findPendingNotifications();
        $users = $userRepository->findUnreadRegistrationNotifications();
        $subscribers = $newsletterRepository->findUnreadNotifications();

        $contactCount = $contactRepository->countUnread();
        $commentCount = $commentRepository->countPending();
        $userCount = $userRepository->countUnreadRegistrationNotifications();
        $newsletterCount = $newsletterRepository->countUnread();

        return [
            'total' => $contactCount + $commentCount + $userCount + $newsletterCount,
            'contacts' => [
                'count' => $contactCount,
                'items' => $contacts,
            ],
            'comments' => [
                'count' => $commentCount,
                'items' => $comments,
            ],
            'users' => [
                'count' => $userCount,
                'items' => $users,
            ],
            'newsletter' => [
                'count' => $newsletterCount,
                'items' => $subscribers,
            ],
        ];
    }
}

<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\AuthorBiographySanitizer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AuthorProfileExtension extends AbstractExtension
{
    public function __construct(
        private readonly AuthorBiographySanitizer $sanitizer,
        private readonly UrlGeneratorInterface $router,
    ) {}

    public function getFilters(): array
    {
        return [new TwigFilter('author_biography', [$this->sanitizer, 'sanitize'], ['is_safe' => ['html']])];
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('author_url', [$this, 'authorUrl'])];
    }

    /** URL tuyệt đối của trang tác giả, null khi tác giả chưa công khai. */
    public function authorUrl(?User $user): ?string
    {
        if (!$user || !$user->isPublicAuthor()) {
            return null;
        }

        return $this->router->generate('author', ['slug' => $user->getAuthorSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}

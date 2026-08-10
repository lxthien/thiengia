<?php

namespace App\Security\Voter;

use App\Entity\News;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * ROLE_EDITOR trở lên sửa/xoá bài viết bất kỳ; ROLE_AUTHOR chỉ sửa/xoá bài
 * viết do chính mình tạo (News::$author).
 */
class NewsVoter extends Voter
{
    public const EDIT = 'NEWS_EDIT';
    public const DELETE = 'NEWS_DELETE';

    public function __construct(private readonly AuthorizationCheckerInterface $authChecker)
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof News;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->authChecker->isGranted('ROLE_EDITOR')) {
            return true;
        }

        /** @var News $news */
        $news = $subject;
        $author = $news->getAuthor();

        return $this->authChecker->isGranted('ROLE_AUTHOR') && $author && $author->getId() === $user->getId();
    }
}

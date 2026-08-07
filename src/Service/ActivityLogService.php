<?php

namespace App\Service;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Service trung tâm để ghi nhật ký hoạt động
 */
class ActivityLogService
{
    private $em;
    private $tokenStorage;
    private $requestStack;

    public function __construct(
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack
    ) {
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
    }

    /**
     * Ghi một activity log
     *
     * @param string      $action      Loại hành động (create, update, delete, toggle, settings)
     * @param string      $entityType  Loại entity (news, page, user, category, ...)
     * @param int|null    $entityId    ID của entity
     * @param string|null $entityTitle Tên/title của entity
     * @param string|null $details     Chi tiết bổ sung
     */
    public function log($action, $entityType, $entityId = null, $entityTitle = null, $details = null)
    {
        try {
            $log = new ActivityLog();
            $log->setAction($action);
            $log->setEntityType($entityType);
            $log->setEntityId($entityId);
            $log->setEntityTitle($entityTitle);
            $log->setDetails($details);

            // Get current user
            $token = $this->tokenStorage->getToken();
            if ($token && is_object($token->getUser())) {
                $user = $token->getUser();
                $log->setUser($user);
                $log->setUsername($user->getUserIdentifier());
            }

            // Get IP address and User Agent
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $log->setIpAddress($request->getClientIp());
                $log->setUserAgent($request->headers->get('User-Agent'));
            }

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Exception $e) {
            // Silently fail — logging should never break the main functionality
        }
    }

    /**
     * Lấy danh sách các trường vừa bị thay đổi (diff) MÀ CHƯA FLUSH
     */
    public function getEntityDiff($entity)
    {
        try {
            $uow = $this->em->getUnitOfWork();
            $uow->computeChangeSets();
            $changes = $uow->getEntityChangeSet($entity);

            // Bỏ qua các field tự động
            unset($changes['updated_at'], $changes['updatedAt'], $changes['last_login']);

            $details = [];
            foreach ($changes as $field => $values) {
                $old = $values[0];
                $new = $values[1];

                // Bỏ qua object (ví dụ relation) hoặc array phức tạp
                if (is_object($old) || is_array($old) || is_object($new) || is_array($new)) {
                    // Nếu là datetime thì parse ra string
                    if ($old instanceof \DateTime || $new instanceof \DateTime) {
                        $oldStr = $old instanceof \DateTime ? $old->format('d/m/Y H:i') : '';
                        $newStr = $new instanceof \DateTime ? $new->format('d/m/Y H:i') : '';
                        $details[] = "$field: [$oldStr] -> [$newStr]";
                    }
                    continue;
                }

                // Cắt chuỗi quá dài (HTML content)
                $oldStr = mb_strlen((string)$old) > 100 ? mb_substr(strip_tags((string)$old), 0, 100) . '...' : (string)$old;
                $newStr = mb_strlen((string)$new) > 100 ? mb_substr(strip_tags((string)$new), 0, 100) . '...' : (string)$new;

                if ($oldStr !== $newStr) {
                    $details[] = "$field: [$oldStr] -> [$newStr]";
                }
            }

            return empty($details) ? null : implode("\n", $details);
        } catch (\Exception $e) {
            return null;
        }
    }
}

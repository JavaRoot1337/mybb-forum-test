<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Security;

use JavaRoot\Resources\Exception\AuthenticationException;
use JavaRoot\Resources\Exception\AuthorizationException;

final class AccessPolicy
{
    public function userId(): int
    {
        global $mybb;

        return (int)($mybb->user['uid'] ?? 0);
    }

    public function requireUser(): int
    {
        $uid = $this->userId();
        if ($uid < 1) {
            throw new AuthenticationException();
        }

        return $uid;
    }

    public function isManager(): bool
    {
        global $mybb;

        return $this->userId() > 0 && !empty($mybb->usergroup['cancp']);
    }

    public function requireManager(): int
    {
        $uid = $this->userId();
        if ($uid < 1 || !$this->isManager()) {
            throw new AuthorizationException();
        }

        return $uid;
    }

    public function requireOwnerOrManager(array $resource): int
    {
        $uid = $this->requireUser();
        if ((int)($resource['uid'] ?? 0) !== $uid && !$this->isManager()) {
            throw new AuthorizationException();
        }

        return $uid;
    }

    public function canView(array $resource): bool
    {
        return ($resource['status'] ?? '') === 'published'
            || (int)($resource['uid'] ?? 0) === $this->userId()
            || $this->isManager();
    }
}

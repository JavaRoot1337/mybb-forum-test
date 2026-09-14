<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Service;

use JavaRoot\Resources\Exception\InvalidRequestException;
use JavaRoot\Resources\Infrastructure\Database\CategoryRepository;
use JavaRoot\Resources\Infrastructure\Database\ResourceRepository;
use JavaRoot\Resources\Security\AccessPolicy;
use JavaRoot\Resources\Security\InputPolicy;

final class ResourceService
{
    public function __construct(
        private ResourceRepository $repository,
        private CategoryRepository $categories,
        private AccessPolicy $access,
    ) {
    }

    public function list(string $sort): array
    {
        return $this->repository->listPublished($sort);
    }

    public function latest(): array
    {
        return $this->repository->latest();
    }

    public function categories(): array
    {
        return $this->categories->all();
    }

    public function canCreate(): bool
    {
        return $this->access->userId() > 0;
    }

    public function requireUser(): int
    {
        return $this->access->requireUser();
    }

    public function find(int $rid): ?array
    {
        return $rid > 0 ? $this->repository->find($rid) : null;
    }

    public function detail(int $rid): array
    {
        $resource = $this->find($rid);
        if (!$resource || !$this->access->canView($resource)) {
            throw new InvalidRequestException();
        }

        return [
            'resource' => $resource,
            'versions' => $this->repository->versions($rid),
            'reviews' => $this->repository->reviews($rid),
            'is_favorite' => $this->access->userId() > 0 && $this->repository->isFavorite($rid, $this->access->userId()),
        ];
    }

    public function create(array $input): array
    {
        $uid = $this->access->requireUser();
        $title = trim((string)($input['title'] ?? ''));
        $summary = trim((string)($input['summary'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $version = trim((string)($input['version'] ?? ''));
        $cid = (int)($input['category'] ?? 0);
        $external = trim((string)($input['external_url'] ?? ''));
        if ($title === '' || $version === '' || !$this->categories->exists($cid) || ($external !== '' && !InputPolicy::externalUrl($external))) {
            throw new InvalidRequestException();
        }

        $now = TIME_NOW;
        $rid = 0;
        $vid = 0;
        try {
            $rid = $this->repository->create([
                'uid' => $uid,
                'cid' => $cid,
                'title' => $title,
                'slug' => InputPolicy::slug($title) . '-' . bin2hex(random_bytes(3)),
                'summary' => $summary,
                'description' => $description,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $vid = $this->repository->createVersion([
                'rid' => $rid,
                'version' => $version,
                'minecraft_versions' => trim((string)($input['minecraft_versions'] ?? '')),
                'changelog' => trim((string)($input['changelog'] ?? '')),
                'status' => 'pending',
                'created_at' => $now,
            ]);
            if ($external !== '') {
                $this->repository->addFile([
                    'vid' => $vid,
                    'kind' => 'external',
                    'external_url' => $external,
                    'security_status' => 'ready',
                    'status' => 'pending',
                ]);
            }
        } catch (\Throwable $exception) {
            $this->repository->deleteCreatedResource($rid, $vid);
            throw $exception;
        }

        return ['rid' => $rid, 'vid' => $vid];
    }

    public function assertOwnerOrManager(int $rid): array
    {
        $resource = $this->find($rid);
        if (!$resource) {
            throw new InvalidRequestException();
        }
        $this->access->requireOwnerOrManager($resource);

        return $resource;
    }

    public function versionBelongsTo(int $rid, int $vid): bool
    {
        return $rid > 0 && $vid > 0 && $this->repository->versionBelongsTo($rid, $vid);
    }

    public function review(int $rid, int $rating, string $message): void
    {
        $uid = $this->access->requireUser();
        $resource = $this->find($rid);
        if (!$resource || $resource['status'] !== 'published') {
            throw new InvalidRequestException();
        }

        $this->repository->upsertReview($rid, $uid, min(5, max(1, $rating)), trim($message));
    }

    public function toggleFavorite(int $rid): void
    {
        $uid = $this->access->requireUser();
        if (!$this->find($rid)) {
            throw new InvalidRequestException();
        }

        $this->repository->toggleFavorite($rid, $uid);
    }

    public function report(int $rid, string $reason): void
    {
        $uid = $this->access->requireUser();
        if (!$this->find($rid)) {
            throw new InvalidRequestException();
        }

        $this->repository->addReport($rid, $uid, trim($reason));
    }

    public function moderate(int $rid, string $status, string $details = ''): void
    {
        $uid = $this->access->requireManager();
        if (!$this->find($rid)) {
            throw new InvalidRequestException();
        }

        $this->repository->moderate($rid, InputPolicy::resourceStatus($status), $uid, $details);
    }

    public function downloadableFile(int $fid): array
    {
        $file = $this->repository->downloadableFile($fid);
        if (!$file) {
            throw new InvalidRequestException();
        }

        return $file;
    }

    public function recordDownload(int $rid, int $fid): void
    {
        $this->repository->recordDownload($rid, $fid, $this->access->userId());
    }

    public function adminPending(): array
    {
        $this->access->requireManager();

        return $this->repository->pending();
    }

    public function adminReports(): array
    {
        $this->access->requireManager();

        return $this->repository->reports();
    }

    public function adminFiles(): array
    {
        $this->access->requireManager();

        return $this->repository->filesForAdmin();
    }

    public function adminUsers(): array
    {
        $this->access->requireManager();

        return $this->repository->users();
    }

    public function closeReport(int $reportId, string $status): void
    {
        $this->access->requireManager();
        if (!in_array($status, ['open', 'closed', 'rejected'], true)) {
            throw new InvalidRequestException();
        }

        $this->repository->closeReport($reportId, $status);
    }

    public function createCategory(string $name, string $slug): void
    {
        $this->access->requireManager();
        $name = trim($name);
        $slug = strtolower(trim($slug));
        if ($name === '' || preg_match('/^[a-z0-9-]+$/', $slug) !== 1 || !$this->categories->create($name, $slug)) {
            throw new InvalidRequestException();
        }
    }
}

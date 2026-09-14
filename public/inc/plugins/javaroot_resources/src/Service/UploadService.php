<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Service;

use JavaRoot\Resources\Config\Settings;
use JavaRoot\Resources\Exception\InvalidRequestException;
use JavaRoot\Resources\Infrastructure\Database\UploadRepository;
use JavaRoot\Resources\Infrastructure\Storage\ArchiveScanner;
use JavaRoot\Resources\Infrastructure\Storage\Storage;
use JavaRoot\Resources\Security\AccessPolicy;
use JavaRoot\Resources\Security\InputPolicy;

final class UploadService
{
    public function __construct(
        private UploadRepository $repository,
        private ResourceService $resources,
        private Storage $storage,
        private ArchiveScanner $scanner,
        private AccessPolicy $access,
    ) {
    }

    public function init(int $rid, int $vid, string $filename, int $size): array
    {
        $uid = $this->access->requireUser();
        $resource = $this->resources->assertOwnerOrManager($rid);
        [$filename, $extension] = InputPolicy::filename($filename);
        if (
            !$resource
            || !$this->resources->versionBelongsTo($rid, $vid)
            || $this->storage->path() === ''
            || !in_array($extension, InputPolicy::UPLOAD_EXTENSIONS, true)
            || $size < 1
            || $size > Settings::fileLimit($extension)
        ) {
            throw new InvalidRequestException();
        }

        $tempDirectory = $this->storage->tempDirectory();
        if (!$this->storage->ensureDirectory($tempDirectory)) {
            throw new InvalidRequestException();
        }

        $token = bin2hex(random_bytes(32));
        $tempPath = $tempDirectory . DIRECTORY_SEPARATOR . $token . '.part';
        $this->repository->create([
            'token' => $token,
            'uid' => $uid,
            'rid' => $rid,
            'vid' => $vid,
            'filename' => $filename,
            'extension' => $extension,
            'expected_size' => $size,
            'received_size' => 0,
            'temp_path' => $tempPath,
            'created_at' => TIME_NOW,
        ]);

        return ['token' => $token, 'chunk_size' => Settings::chunkSize(), 'received_size' => 0];
    }

    public function status(string $token): array
    {
        $upload = $this->findOwned($token);

        return [
            'token' => $token,
            'filename' => $upload['filename'],
            'expected_size' => (int)$upload['expected_size'],
            'received_size' => (int)$upload['received_size'],
            'chunk_size' => Settings::chunkSize(),
        ];
    }

    public function chunk(string $token, int $offset, int $length): array
    {
        $upload = $this->findOwned($token);
        if (
            $offset !== (int)$upload['received_size']
            || $length < 1
            || $length > Settings::chunkSize()
            || $offset + $length > (int)$upload['expected_size']
        ) {
            throw new InvalidRequestException(409);
        }
        if ($this->storage->writeChunk($upload['temp_path'], $offset, $length) === false) {
            throw new InvalidRequestException();
        }

        $received = $offset + $length;
        $this->repository->updateReceived($token, $received);

        return ['received_size' => $received];
    }

    public function finalize(string $token): void
    {
        $upload = $this->findOwned($token);
        if ((int)$upload['received_size'] !== (int)$upload['expected_size'] || !is_file($upload['temp_path'])) {
            throw new InvalidRequestException(409);
        }

        $this->resources->assertOwnerOrManager((int)$upload['rid']);
        if (!$this->resources->versionBelongsTo((int)$upload['rid'], (int)$upload['vid'])) {
            throw new InvalidRequestException();
        }

        $destination = $this->storage->moveToStorage($upload['temp_path'], (int)$upload['rid'], (int)$upload['vid'], $upload['extension']);
        if ($destination === false) {
            throw new InvalidRequestException();
        }

        $securityStatus = $this->scanner->inspect($destination, $upload['extension'], (int)$upload['expected_size']);
        if ($securityStatus === false) {
            $this->storage->remove($destination);
            throw new InvalidRequestException();
        }

        try {
            $this->repository->addFile([
                'vid' => (int)$upload['vid'],
                'kind' => 'local',
                'filename' => $upload['filename'],
                'path' => $destination,
                'size' => (int)$upload['expected_size'],
                'sha256' => (string)hash_file('sha256', $destination),
                'security_status' => $securityStatus,
                'status' => 'pending',
            ]);
        } catch (\Throwable $exception) {
            $this->storage->remove($destination);
            throw $exception;
        }
        $this->repository->delete($token);
    }

    private function findOwned(string $token): array
    {
        $uid = $this->access->requireUser();
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
        $upload = $this->repository->find($token);
        if (!$upload || (int)$upload['uid'] !== $uid || (int)$upload['created_at'] < TIME_NOW - 86400) {
            throw new InvalidRequestException();
        }

        return $upload;
    }
}

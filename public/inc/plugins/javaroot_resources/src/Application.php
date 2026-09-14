<?php

declare(strict_types=1);

namespace JavaRoot\Resources;

use JavaRoot\Resources\Infrastructure\Database\CategoryRepository;
use JavaRoot\Resources\Infrastructure\Database\PreferenceRepository;
use JavaRoot\Resources\Infrastructure\Database\ResourceRepository;
use JavaRoot\Resources\Infrastructure\Database\UploadRepository;
use JavaRoot\Resources\Infrastructure\Storage\ArchiveScanner;
use JavaRoot\Resources\Infrastructure\Storage\Storage;
use JavaRoot\Resources\Lifecycle\PluginLifecycle;
use JavaRoot\Resources\Security\AccessPolicy;
use JavaRoot\Resources\Service\ResourceService;
use JavaRoot\Resources\Service\ThemeService;
use JavaRoot\Resources\Service\UploadService;

final class Application
{
    private ?ResourceService $resources = null;
    private ?UploadService $uploads = null;
    private ?PluginLifecycle $lifecycle = null;
    private ?ThemeService $themes = null;

    public function resources(): ResourceService
    {
        return $this->resources ??= new ResourceService(
            new ResourceRepository(),
            new CategoryRepository(),
            new AccessPolicy(),
        );
    }

    public function uploads(): UploadService
    {
        return $this->uploads ??= new UploadService(
            new UploadRepository(),
            $this->resources(),
            new Storage(),
            new ArchiveScanner(),
            new AccessPolicy(),
        );
    }

    public function lifecycle(): PluginLifecycle
    {
        return $this->lifecycle ??= new PluginLifecycle();
    }

    public function themes(): ThemeService
    {
        return $this->themes ??= new ThemeService(new PreferenceRepository());
    }
}

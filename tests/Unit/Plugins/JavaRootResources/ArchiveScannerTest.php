<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Plugins\JavaRootResources;

use JavaRoot\Resources\Infrastructure\Storage\ArchiveScanner;
use MyBB\Tests\Unit\TestCase;

require_once __DIR__ . '/../../../../public/inc/plugins/javaroot_resources/src/Autoloader.php';

\JavaRoot\Resources\Autoloader::register();

final class ArchiveScannerTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testInvalidJarSignatureIsRejected(): void
    {
        $path = $this->temporaryFile('not-a-jar');

        self::assertFalse((new ArchiveScanner())->inspect($path, 'jar', filesize($path)));
    }

    public function testSafeZipIsReady(): void
    {
        $path = $this->temporaryFile();
        $zip = new \ZipArchive();
        self::assertSame(true, $zip->open($path));
        $zip->addFromString('plugin.yml', 'name: Example');
        $zip->close();

        self::assertSame('ready', (new ArchiveScanner())->inspect($path, 'zip', filesize($path)));
    }

    private function temporaryFile(string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'javaroot-');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}

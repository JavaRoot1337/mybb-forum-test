<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Plugins\JavaRootResources;

use JavaRoot\Resources\Exception\InvalidRequestException;
use JavaRoot\Resources\Security\InputPolicy;
use MyBB\Tests\Unit\TestCase;

require_once __DIR__ . '/../../../../public/inc/plugins/javaroot_resources/src/Autoloader.php';

\JavaRoot\Resources\Autoloader::register();

final class InputPolicyTest extends TestCase
{
    public function testExternalUrlOnlyAllowsConfiguredHttpsHosts(): void
    {
        self::assertTrue(InputPolicy::externalUrl('https://github.com/example/project/releases/latest'));
        self::assertTrue(InputPolicy::externalUrl('https://modrinth.com/plugin/example'));
        self::assertFalse(InputPolicy::externalUrl('http://github.com/example/project'));
        self::assertFalse(InputPolicy::externalUrl('https://evil.example/download.jar'));
    }

    public function testSameOriginReturnUrlRejectsForeignHost(): void
    {
        self::assertTrue(InputPolicy::sameOrigin('https://forum.example/settings', 'https://forum.example'));
        self::assertFalse(InputPolicy::sameOrigin('https://evil.example/settings', 'https://forum.example'));
        self::assertSame('/resources.php', InputPolicy::safeReturnUrl('/resources.php', 'https://forum.example'));
        self::assertSame('https://forum.example/index.php', InputPolicy::safeReturnUrl('https://evil.example', 'https://forum.example'));
    }

    public function testFilenameNormalizesControlCharactersAndExtension(): void
    {
        [$filename, $extension] = InputPolicy::filename("../build\x00.jar");

        self::assertSame('build_.jar', $filename);
        self::assertSame('jar', $extension);
    }

    public function testThemeAndStatusAllowListsRejectUnknownValues(): void
    {
        self::assertSame('dark', InputPolicy::theme('dark'));
        self::assertSame('published', InputPolicy::resourceStatus('published'));
        $this->expectException(InvalidRequestException::class);
        InputPolicy::theme('solarized');
    }
}

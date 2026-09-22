<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 *
 * SCRUM-101 — Validates composer.json files declare web-token/jwt-library (not jwt-framework).
 */

declare(strict_types=1);

namespace Magento\JwtFrameworkAdapter\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComposerDependencyValidationTest extends TestCase
{
    private const ROOT_COMPOSER = __DIR__ . '/../../../../../../composer.json';
    private const MODULE_COMPOSER = __DIR__ . '/../../../composer.json';

    public static function composerFileProvider(): array
    {
        return [
            'root composer.json' => [self::ROOT_COMPOSER],
            'module composer.json' => [self::MODULE_COMPOSER],
        ];
    }

    #[DataProvider('composerFileProvider')]
    public function testComposerFileIsValidJson(string $composerPath): void
    {
        $this->assertFileExists($composerPath);
        $content = file_get_contents($composerPath);
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'composer.json must be valid JSON');
        $this->assertIsArray($decoded);
    }

    #[DataProvider('composerFileProvider')]
    public function testRequiresJwtLibrary(string $composerPath): void
    {
        $decoded = json_decode(file_get_contents($composerPath), true);
        $this->assertArrayHasKey('require', $decoded);
        $this->assertArrayHasKey(
            'web-token/jwt-library',
            $decoded['require'],
            'composer.json must require web-token/jwt-library'
        );
    }

    #[DataProvider('composerFileProvider')]
    public function testDoesNotRequireJwtFramework(string $composerPath): void
    {
        $decoded = json_decode(file_get_contents($composerPath), true);
        $require = $decoded['require'] ?? [];
        $this->assertArrayNotHasKey(
            'web-token/jwt-framework',
            $require,
            'composer.json must not require web-token/jwt-framework'
        );
    }

    #[DataProvider('composerFileProvider')]
    public function testJwtLibraryVersionConstraint(string $composerPath): void
    {
        $decoded = json_decode(file_get_contents($composerPath), true);
        $constraint = $decoded['require']['web-token/jwt-library'] ?? null;
        $this->assertNotNull($constraint);
        $this->assertStringContainsString('^4.0', $constraint);
    }

    public function testModuleNamePreserved(): void
    {
        $decoded = json_decode(file_get_contents(self::MODULE_COMPOSER), true);
        $this->assertEquals(
            'magento/module-jwt-framework-adapter',
            $decoded['name'],
            'Module package name must remain magento/module-jwt-framework-adapter for backward compatibility'
        );
    }

    public function testModuleAutoloadNamespace(): void
    {
        $decoded = json_decode(file_get_contents(self::MODULE_COMPOSER), true);
        $psr4 = $decoded['autoload']['psr-4'] ?? [];
        $this->assertArrayHasKey(
            'Magento\\JwtFrameworkAdapter\\',
            $psr4,
            'Module PSR-4 autoload namespace must be preserved'
        );
    }
}

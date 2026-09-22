<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 *
 * SCRUM-101 — Verifies all 14 JWS algorithms resolve from web-token/jwt-library.
 */

declare(strict_types=1);

namespace Magento\JwtFrameworkAdapter\Test\Unit\Model;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\EdDSA;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\HS384;
use Jose\Component\Signature\Algorithm\HS512;
use Jose\Component\Signature\Algorithm\None;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\PS384;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Magento\JwtFrameworkAdapter\Model\JwsAlgorithmManagerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JwsAlgorithmManagerFactoryTest extends TestCase
{
    private JwsAlgorithmManagerFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new JwsAlgorithmManagerFactory();
    }

    public function testCreateReturnsAlgorithmManager(): void
    {
        $manager = $this->factory->create();
        $this->assertInstanceOf(AlgorithmManager::class, $manager);
    }

    public static function jwsAlgorithmProvider(): array
    {
        return [
            'HS256' => ['HS256', HS256::class],
            'HS384' => ['HS384', HS384::class],
            'HS512' => ['HS512', HS512::class],
            'RS256' => ['RS256', RS256::class],
            'RS384' => ['RS384', RS384::class],
            'RS512' => ['RS512', RS512::class],
            'PS256' => ['PS256', PS256::class],
            'PS384' => ['PS384', PS384::class],
            'PS512' => ['PS512', PS512::class],
            'ES256' => ['ES256', ES256::class],
            'ES384' => ['ES384', ES384::class],
            'ES512' => ['ES512', ES512::class],
            'EdDSA' => ['EdDSA', EdDSA::class],
            'none'  => ['none',  None::class],
        ];
    }

    #[DataProvider('jwsAlgorithmProvider')]
    public function testAlgorithmIsRegistered(string $name, string $expectedClass): void
    {
        $manager = $this->factory->create();
        $algorithm = $manager->get($name);
        $this->assertInstanceOf($expectedClass, $algorithm);
        $this->assertEquals($name, $algorithm->name());
    }

    public function testExactAlgorithmCount(): void
    {
        $manager = $this->factory->create();
        $this->assertCount(14, $manager->list());
    }

    public function testNoExperimentalAlgorithmsPresent(): void
    {
        $manager = $this->factory->create();
        $registered = $manager->list();
        $experimentalAlgorithms = ['Blake2b', 'ES256K', 'HS1', 'RS1'];
        foreach ($experimentalAlgorithms as $experimental) {
            $this->assertNotContains(
                $experimental,
                $registered,
                "Experimental algorithm $experimental must not be registered"
            );
        }
    }

    public function testJoseSignatureClassesResolve(): void
    {
        $this->assertTrue(class_exists(HS256::class));
        $this->assertTrue(class_exists(RS256::class));
        $this->assertTrue(class_exists(PS256::class));
        $this->assertTrue(class_exists(ES256::class));
        $this->assertTrue(class_exists(EdDSA::class));
        $this->assertTrue(class_exists(None::class));
    }

    public function testJoseCoreClassesResolve(): void
    {
        $this->assertTrue(class_exists(AlgorithmManager::class));
        $this->assertTrue(class_exists(\Jose\Component\Core\JWK::class));
        $this->assertTrue(class_exists(\Jose\Component\Core\JWKSet::class));
    }
}

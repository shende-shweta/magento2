<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 *
 * SCRUM-101 — Verifies all JWE key-encryption and content-encryption algorithms resolve from web-token/jwt-library.
 */

declare(strict_types=1);

namespace Magento\JwtFrameworkAdapter\Test\Unit\Model;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128CBCHS256;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128GCM;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A192CBCHS384;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A192GCM;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256CBCHS512;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A128GCMKW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A128KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A192GCMKW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A192KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256GCMKW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\Dir;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHES;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA128KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA192KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\PBES2HS256A128KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\PBES2HS384A192KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\PBES2HS512A256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
use Magento\JwtFrameworkAdapter\Model\JweAlgorithmManagerFactory;
use Magento\JwtFrameworkAdapter\Model\JweContentAlgorithmManagerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JweAlgorithmManagerFactoryTest extends TestCase
{
    private JweAlgorithmManagerFactory $keyFactory;
    private JweContentAlgorithmManagerFactory $contentFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyFactory = new JweAlgorithmManagerFactory();
        $this->contentFactory = new JweContentAlgorithmManagerFactory();
    }

    public function testKeyEncryptionFactoryReturnsAlgorithmManager(): void
    {
        $manager = $this->keyFactory->create();
        $this->assertInstanceOf(AlgorithmManager::class, $manager);
    }

    public function testContentEncryptionFactoryReturnsAlgorithmManager(): void
    {
        $manager = $this->contentFactory->create();
        $this->assertInstanceOf(AlgorithmManager::class, $manager);
    }

    public static function jweKeyAlgorithmProvider(): array
    {
        return [
            'RSA-OAEP'          => ['RSA-OAEP',          RSAOAEP::class],
            'RSA-OAEP-256'      => ['RSA-OAEP-256',      RSAOAEP256::class],
            'A128KW'            => ['A128KW',             A128KW::class],
            'A192KW'            => ['A192KW',             A192KW::class],
            'A256KW'            => ['A256KW',             A256KW::class],
            'dir'               => ['dir',                Dir::class],
            'ECDH-ES'           => ['ECDH-ES',            ECDHES::class],
            'ECDH-ES+A128KW'    => ['ECDH-ES+A128KW',     ECDHESA128KW::class],
            'ECDH-ES+A192KW'    => ['ECDH-ES+A192KW',     ECDHESA192KW::class],
            'ECDH-ES+A256KW'    => ['ECDH-ES+A256KW',     ECDHESA256KW::class],
            'A128GCMKW'         => ['A128GCMKW',          A128GCMKW::class],
            'A192GCMKW'         => ['A192GCMKW',          A192GCMKW::class],
            'A256GCMKW'         => ['A256GCMKW',          A256GCMKW::class],
            'PBES2-HS256+A128KW' => ['PBES2-HS256+A128KW', PBES2HS256A128KW::class],
            'PBES2-HS384+A192KW' => ['PBES2-HS384+A192KW', PBES2HS384A192KW::class],
            'PBES2-HS512+A256KW' => ['PBES2-HS512+A256KW', PBES2HS512A256KW::class],
        ];
    }

    #[DataProvider('jweKeyAlgorithmProvider')]
    public function testKeyAlgorithmIsRegistered(string $name, string $expectedClass): void
    {
        $manager = $this->keyFactory->create();
        $algorithm = $manager->get($name);
        $this->assertInstanceOf($expectedClass, $algorithm);
        $this->assertEquals($name, $algorithm->name());
    }

    public function testKeyAlgorithmExactCount(): void
    {
        $manager = $this->keyFactory->create();
        $this->assertCount(16, $manager->list());
    }

    public static function jweContentAlgorithmProvider(): array
    {
        return [
            'A128CBC-HS256' => ['A128CBC-HS256', A128CBCHS256::class],
            'A192CBC-HS384' => ['A192CBC-HS384', A192CBCHS384::class],
            'A256CBC-HS512' => ['A256CBC-HS512', A256CBCHS512::class],
            'A128GCM'       => ['A128GCM',       A128GCM::class],
            'A192GCM'       => ['A192GCM',       A192GCM::class],
            'A256GCM'       => ['A256GCM',       A256GCM::class],
        ];
    }

    #[DataProvider('jweContentAlgorithmProvider')]
    public function testContentAlgorithmIsRegistered(string $name, string $expectedClass): void
    {
        $manager = $this->contentFactory->create();
        $algorithm = $manager->get($name);
        $this->assertInstanceOf($expectedClass, $algorithm);
        $this->assertEquals($name, $algorithm->name());
    }

    public function testContentAlgorithmExactCount(): void
    {
        $manager = $this->contentFactory->create();
        $this->assertCount(6, $manager->list());
    }

    public function testJoseEncryptionClassesResolve(): void
    {
        $this->assertTrue(class_exists(\Jose\Component\Encryption\JWEBuilder::class));
        $this->assertTrue(class_exists(\Jose\Component\Encryption\JWELoader::class));
        $this->assertTrue(class_exists(\Jose\Component\Encryption\JWEDecrypter::class));
        $this->assertTrue(class_exists(\Jose\Component\Encryption\Serializer\JWESerializerManager::class));
        $this->assertTrue(class_exists(\Jose\Component\Encryption\Serializer\CompactSerializer::class));
    }

    public function testJoseSignatureSerializerClassesResolve(): void
    {
        $this->assertTrue(class_exists(\Jose\Component\Signature\JWSBuilder::class));
        $this->assertTrue(class_exists(\Jose\Component\Signature\JWSLoader::class));
        $this->assertTrue(class_exists(\Jose\Component\Signature\JWSVerifier::class));
        $this->assertTrue(class_exists(\Jose\Component\Signature\Serializer\JWSSerializerManager::class));
        $this->assertTrue(class_exists(\Jose\Component\Signature\Serializer\CompactSerializer::class));
    }

    public function testNoExperimentalEncryptionAlgorithms(): void
    {
        $keyAlgorithms = $this->keyFactory->create()->list();
        $contentAlgorithms = $this->contentFactory->create()->list();
        $all = array_merge($keyAlgorithms, $contentAlgorithms);

        $experimentalAlgorithms = [
            'A128CTR', 'A192CTR', 'A256CTR',
            'A128CCM-16-64', 'A128CCM-16-128', 'A128CCM-64-64', 'A128CCM-64-128',
            'A256CCM-16-64', 'A256CCM-16-128', 'A256CCM-64-64', 'A256CCM-64-128',
            'C20P', 'XC20P',
        ];
        foreach ($experimentalAlgorithms as $experimental) {
            $this->assertNotContains(
                $experimental,
                $all,
                "Experimental algorithm $experimental must not be registered"
            );
        }
    }
}

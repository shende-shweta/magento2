<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Framework\App\Test\Unit\Cache;

use Magento\Framework\App\Cache\State;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\DeploymentConfig\Writer;
use Magento\Framework\Config\File\ConfigFilePool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;

class StateTest extends TestCase
{
    use MockCreationTrait;
    /**
     * @var MockObject
     */
    private $config;

    /**
     * @var MockObject
     */
    private $writer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(DeploymentConfig::class);
        $this->writer = $this->createPartialMockWithReflection(
            Writer::class,
            ['update', 'saveConfig']
        );
    }

    /**     * @param bool $expectedIsEnabled
     */
    #[DataProvider('isEnabledDataProvider')]
    public function testIsEnabled($cacheType, $config, $banAll, $expectedIsEnabled)
    {
        $model = new State($this->config, $this->writer, $banAll);
        if ($banAll) {
            $this->config->expects($this->never())->method('getConfigData');
        } else {
            $this->config->expects($this->once())->method('getConfigData')->willReturn($config);
        }
        $this->writer->expects($this->never())->method('update');
        $actualIsEnabled = $model->isEnabled($cacheType);
        $this->assertEquals($expectedIsEnabled, $actualIsEnabled);
    }

    /**
     * @return array
     */
    public static function isEnabledDataProvider()
    {
        return [
            'enabled' => [
                'cacheType' => 'cache_type',
                'config' => ['some_type' => false, 'cache_type' => true],
                'banAll' => false,
                'expectedIsEnabled' => true,
            ],
            'disabled' => [
                'cacheType' => 'cache_type',
                'config' => ['some_type' => true, 'cache_type' => false],
                'banAll' => false,
                'expectedIsEnabled' => false,
            ],
            'unknown is disabled' => [
                'cacheType' => 'unknown_cache_type',
                'config' => ['some_type' => true],
                'banAll' => false,
                'expectedIsEnabled' => false,
            ],
            'disabled, when all caches are banned' => [
                'cacheType' => 'cache_type',
                'config' => ['cache_type' => true],
                'banAll' => true,
                'expectedIsEnabled' => false,
            ]
        ];
    }

    public function testSetEnabled()
    {
        $model = new State($this->config, $this->writer);
        $this->config->expects($this->once())->method('getConfigData')->willReturn([]);
        $this->assertFalse($model->isEnabled('cache_type'));
        $model->setEnabled('cache_type', true);
        $this->assertTrue($model->isEnabled('cache_type'));
        $model->setEnabled('cache_type', false);
        $this->assertFalse($model->isEnabled('cache_type'));
    }

    public function testPersist()
    {
        $model = new State($this->config, $this->writer);
        $this->config->expects($this->atLeastOnce())
            ->method('getConfigData')
            ->willReturn(['test_cache_type' => true]);
        $model->setEnabled('test_cache_type', false);
        $configValue = [ConfigFilePool::APP_ENV => ['cache_types' => ['test_cache_type' => 0]]];
        $this->writer->expects($this->once())->method('saveConfig')->with($configValue);
        $model->persist();
    }

    public function testPersistDoesNothingWhenNoCacheTypeWasMutated(): void
    {
        $this->config->expects($this->once())
            ->method('getConfigData')
            ->willReturn(['full_page' => 1, 'config' => 1]);
        $model = new State($this->config, $this->writer);
        $this->writer->expects($this->never())->method('saveConfig');
        $model->persist();
    }

    /**
     * SCRUM-100: disabling one cache type must not persist the full merged map to env.php.
     */
    public function testPersistWritesOnlyMutatedCacheTypes(): void
    {
        $mergedConfig = [
            'full_page' => 1,
            'config' => 1,
            'layout' => 0,
        ];
        $this->config->expects($this->atLeastOnce())
            ->method('getConfigData')
            ->willReturn($mergedConfig);

        $model = new State($this->config, $this->writer);
        $model->setEnabled('full_page', false);

        $expected = [
            ConfigFilePool::APP_ENV => [
                'cache_types' => ['full_page' => 0],
            ],
        ];
        $this->writer->expects($this->once())->method('saveConfig')->with($expected);
        $model->persist();
    }

    public function testPersistAfterReEnableWritesOnlyThatType(): void
    {
        $this->config->expects($this->atLeastOnce())
            ->method('getConfigData')
            ->willReturn(['full_page' => 0, 'config' => 1]);

        $model = new State($this->config, $this->writer);
        $model->setEnabled('full_page', true);

        $expected = [
            ConfigFilePool::APP_ENV => [
                'cache_types' => ['full_page' => 1],
            ],
        ];
        $this->writer->expects($this->once())->method('saveConfig')->with($expected);
        $model->persist();
    }

    public function testPersistIncludesAllTypesMutatedSinceLastPersist(): void
    {
        $this->config->expects($this->atLeastOnce())
            ->method('getConfigData')
            ->willReturn(['full_page' => 1, 'config' => 1, 'layout' => 1]);

        $model = new State($this->config, $this->writer);
        $model->setEnabled('full_page', false);
        $model->setEnabled('layout', false);

        $expected = [
            ConfigFilePool::APP_ENV => [
                'cache_types' => [
                    'full_page' => 0,
                    'layout' => 0,
                ],
            ],
        ];
        $this->writer->expects($this->once())->method('saveConfig')->with($expected);
        $model->persist();
    }

    public function testSecondPersistAfterFirstPersistRequiresNewMutation(): void
    {
        $this->config->expects($this->atLeastOnce())
            ->method('getConfigData')
            ->willReturn(['full_page' => 1]);

        $model = new State($this->config, $this->writer);
        $model->setEnabled('full_page', false);
        $this->writer->expects($this->exactly(1))->method('saveConfig');
        $model->persist();
        $model->persist();
    }

    public function testResetStateClearsMutatedTracking(): void
    {
        $this->config->expects($this->atLeastOnce())
            ->method('getConfigData')
            ->willReturn(['full_page' => 1]);

        $model = new State($this->config, $this->writer);
        $model->setEnabled('full_page', false);
        $model->_resetState();

        $this->writer->expects($this->never())->method('saveConfig');
        $model->persist();
    }
}
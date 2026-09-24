<?php
/**
 * Copyright 2014 Adobe
 * All Rights Reserved.
 */
namespace Magento\Framework\App\Cache;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\DeploymentConfig\Writer;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Cache State
 *
 * SCRUM-105 (cache CLI): persist only cache types mutated via setEnabled() into env.php.
 * Root cause: unscoped persist promoted the full merged cache_types map from config.php.
 *
 * Upstream reference: Adobe/magento2#41275 proposes the same scoped-persist behavior using
 * inline mutation tracking ($mutatedCacheTypes). This fork is not a byte-for-byte copy: it uses
 * $cacheTypesPendingEnvPersist, extractPendingEnvCacheStatuses(), and SCRUM-105 regression tests.
 * Historical cross-check: unmerged SCRUM-100 hotfix (PR #3) — same symptom class.
 * Jira: SCRUM-105 duplicates open SCRUM-18; this branch is the shared fix track for both tickets.
 */
class State implements StateInterface, ResetAfterRequestInterface
{
    /**
     * Disallow cache
     */
    public const PARAM_BAN_CACHE = 'global_ban_use_cache';

    /**
     * Deployment config key
     */
    public const CACHE_KEY = 'cache_types';

    /**
     * Deployment configuration
     *
     * @var DeploymentConfig
     *  phpcs:disable Magento2.Commenting.ClassPropertyPHPDocFormatting
     */
    private readonly DeploymentConfig $config;

    /**
     * Deployment configuration storage writer
     *
     * @var Writer
     *
     * phpcs:disable Magento2.Commenting.ClassPropertyPHPDocFormatting
     */
    private readonly Writer $writer;

    /**
     * Associative array of cache type codes and their statuses (enabled/disabled)
     *
     * @var array|null
     */
    private ?array $statuses = null;

    /**
     * Whether all cache types are forced to be disabled
     *
     * @var bool
     * phpcs:disable Magento2.Commenting.ClassPropertyPHPDocFormatting
     */
    private readonly bool $banAll;

    /**
     * Cache type codes queued for env.php persist since the last persist() call
     *
     * @var array<string, true>
     */
    private array $cacheTypesPendingEnvPersist = [];

    /**
     * Constructor
     *
     * @param DeploymentConfig $config
     * @param Writer $writer
     * @param bool $banAll
     */
    public function __construct(DeploymentConfig $config, Writer $writer, $banAll = false)
    {
        $this->config = $config;
        $this->writer = $writer;
        $this->banAll = $banAll;
    }

    /**
     * Whether a cache type is enabled or not at the moment
     *
     * @param string $cacheType
     * @return bool
     */
    public function isEnabled($cacheType): bool
    {
        $this->load();
        return (bool)($this->statuses[$cacheType] ?? false);
    }

    /**
     * Enable/disable a cache type in run-time
     *
     * @param string $cacheType
     * @param bool $isEnabled
     * @return void
     */
    public function setEnabled($cacheType, $isEnabled): void
    {
        $this->load();
        $this->statuses[$cacheType] = (int)$isEnabled;
        $this->cacheTypesPendingEnvPersist[$cacheType] = true;
    }

    /**
     * @inheritdoc
     */
    public function persist(): void
    {
        $this->load();
        $pendingStatuses = $this->extractPendingEnvCacheStatuses();
        if ($pendingStatuses === []) {
            return;
        }
        $this->writer->saveConfig([ConfigFilePool::APP_ENV => [self::CACHE_KEY => $pendingStatuses]]);
        $this->cacheTypesPendingEnvPersist = [];
    }

    /**
     * Subset of in-memory statuses that should be written to env.php on this persist() call.
     *
     * @return array<string, int>
     */
    private function extractPendingEnvCacheStatuses(): array
    {
        if ($this->cacheTypesPendingEnvPersist === []) {
            return [];
        }
        return array_intersect_key($this->statuses, $this->cacheTypesPendingEnvPersist);
    }

    /**
     * Load statuses (enabled/disabled) of cache types
     *
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\RuntimeException
     */
    private function load(): void
    {
        if (null === $this->statuses) {
            $this->statuses = [];
            if ($this->banAll) {
                return;
            }
            $this->statuses = $this->config->getConfigData(self::CACHE_KEY) ?: [];
        }
    }

    /**
     * @inheritdoc
     */
    public function _resetState(): void
    {
        $this->statuses = null;
        $this->cacheTypesPendingEnvPersist = [];
    }
}

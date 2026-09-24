<?php
/**
 * Copyright 2014 Adobe
 * All Rights Reserved.
 */
namespace Magento\Framework\App\Cache;

/**
 * @api
 * @since 100.0.2
 */
interface StateInterface
{
    /**
     * Whether a cache type is enabled at the moment or not
     *
     * @param string $cacheType
     * @return bool
     */
    public function isEnabled($cacheType);

    /**
     * Enable/disable a cache type in run-time
     *
     * @param string $cacheType
     * @param bool $isEnabled
     * @return void
     */
    public function setEnabled($cacheType, $isEnabled);

    /**
     * Persist cache type enable/disable changes made via setEnabled() to deployment storage.
     *
     * Implementation writes only cache types that were modified through setEnabled() since
     * the last successful persist() into the environment-specific config (typically
     * app/etc/env.php). If no types were modified, this method performs no write.
     *
     * @return void
     */
    public function persist();
}

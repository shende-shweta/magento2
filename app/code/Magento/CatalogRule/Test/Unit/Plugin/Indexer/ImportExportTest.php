<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogImportExport\Test\Unit\Model\Indexer\Product\Eav\Plugin;

use Magento\Catalog\Model\Indexer\Product\Eav\Processor;
use Magento\CatalogImportExport\Model\Indexer\Product\Eav\Plugin\Import as EavImportPlugin;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\ImportExport\Model\Import;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * SCRUM-115: afterImportSource must only invalidate the catalog_product EAV
 * indexer for catalog_product imports, not for every scheduled import type.
 */
class ImportTest extends TestCase
{
    /** @var IndexerRegistry|MockObject */
    private $indexerRegistryMock;

    /** @var IndexerInterface|MockObject */
    private $indexerMock;

    /** @var EavImportPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->indexerRegistryMock = $this->createMock(IndexerRegistry::class);
        $this->indexerMock = $this->createMock(IndexerInterface::class);
        $this->plugin = new EavImportPlugin($this->indexerRegistryMock);
    }

    /**
     * SCRUM-115 bug-fix test: a non-catalog_product scheduled import (e.g.
     * "customer") must NOT invalidate the catalog_product EAV indexer.
     */
    public function testAfterImportSourceDoesNotInvalidateForNonCatalogProductEntity(): void
    {
        $subject = $this->createMock(Import::class);
        $subject->method('getEntity')->willReturn('customer');

        $this->indexerRegistryMock->expects($this->never())->method('get');
        $this->indexerMock->expects($this->never())->method('invalidate');

        $result = $this->plugin->afterImportSource($subject, $subject);

        $this->assertSame($subject, $result);
    }

    /**
     * Regression: a catalog_product scheduled import must still invalidate
     * the EAV indexer exactly once (intended pre-existing behavior).
     */
    public function testAfterImportSourceInvalidatesForCatalogProductEntity(): void
    {
        $subject = $this->createMock(Import::class);
        $subject->method('getEntity')->willReturn('catalog_product');

        $this->indexerRegistryMock->expects($this->once())
            ->method('get')
            ->with(Processor::INDEXER_ID)
            ->willReturn($this->indexerMock);
        $this->indexerMock->expects($this->once())->method('invalidate');

        $result = $this->plugin->afterImportSource($subject, $subject);

        $this->assertSame($subject, $result);
    }

    /**
     * Regression: repeated catalog_product imports each invalidate once —
     * no leaked state / double-invalidate across plugin invocations.
     */
    public function testAfterImportSourceIsIdempotentAcrossMultipleCalls(): void
    {
        $subject = $this->createMock(Import::class);
        $subject->method('getEntity')->willReturn('catalog_product');

        $this->indexerRegistryMock->expects($this->exactly(2))
            ->method('get')
            ->with(Processor::INDEXER_ID)
            ->willReturn($this->indexerMock);
        $this->indexerMock->expects($this->exactly(2))->method('invalidate');

        $this->plugin->afterImportSource($subject, $subject);
        $this->plugin->afterImportSource($subject, $subject);
    }

    /**
     * Edge case: entity comparison must be exact — a similarly named but
     * different entity ("catalog_product_attribute") must not false-match.
     */
    public function testAfterImportSourceDoesNotInvalidateForSimilarButDifferentEntity(): void
    {
        $subject = $this->createMock(Import::class);
        $subject->method('getEntity')->willReturn('catalog_product_attribute');

        $this->indexerRegistryMock->expects($this->never())->method('get');

        $this->plugin->afterImportSource($subject, $subject);
    }

    /**
     * Edge case: empty/unset entity type must not accidentally match and
     * must not trigger a fatal error.
     */
    public function testAfterImportSourceHandlesEmptyEntityGracefully(): void
    {
        $subject = $this->createMock(Import::class);
        $subject->method('getEntity')->willReturn('');

        $this->indexerRegistryMock->expects($this->never())->method('get');

        $result = $this->plugin->afterImportSource($subject, $subject);

        $this->assertSame($subject, $result);
    }
}
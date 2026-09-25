<?php
/**
 * Copyright 2014 Adobe
 * All Rights Reserved.
 */
namespace Magento\CatalogImportExport\Model\Indexer\Product\Eav\Plugin;

class Import
{
    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Eav\Processor
     */
    protected $_indexerEavProcessor;

    /**
     * @param \Magento\Catalog\Model\Indexer\Product\Eav\Processor $indexerEavProcessor
     */
    public function __construct(\Magento\Catalog\Model\Indexer\Product\Eav\Processor $indexerEavProcessor)
    {
        $this->_indexerEavProcessor = $indexerEavProcessor;
    }

    /**
     * After import handler
     *
     * Only invalidate the catalog_product EAV indexer when the imported entity
     * is actually catalog_product; unrelated entity imports must not trigger
     * a reindex.
     *
     * @param \Magento\ImportExport\Model\Import $subject
     * @param Object $import
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterImportSource(\Magento\ImportExport\Model\Import $subject, $import)
    {
        if ($subject->getEntity() === 'catalog_product'
            && !$this->_indexerEavProcessor->isIndexerScheduled()
        ) {
            $this->_indexerEavProcessor->markIndexerAsInvalid();
        }
        return $import;
    }
}

<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\StockRecord;

final class StockRecordService
{
    public function assertApplyDelta(Product $product, int $delta): void
    {
        $new = $product->getStock() + $delta;
        if ($new < 0) {
            throw new \InvalidArgumentException(
                sprintf('Stock cannot go below zero (current %d, change %d).', $product->getStock(), $delta)
            );
        }
    }

    public function applyDelta(Product $product, int $delta): void
    {
        $this->assertApplyDelta($product, $delta);
        $product->setStock($product->getStock() + $delta);
    }

    public function persistNew(StockRecord $record): void
    {
        $product = $record->getProduct();
        if (!$product) {
            throw new \InvalidArgumentException('Product is required.');
        }
        $this->applyDelta($product, $record->getQuantityDelta());
    }

    public function revert(StockRecord $record): void
    {
        $product = $record->getProduct();
        if (!$product) {
            return;
        }
        $this->applyDelta($product, -$record->getQuantityDelta());
    }

    public function replaceDelta(StockRecord $record, int $oldDelta, int $newDelta): void
    {
        $product = $record->getProduct();
        if (!$product) {
            throw new \InvalidArgumentException('Product is required.');
        }
        $this->assertApplyDelta($product, -$oldDelta);
        $product->setStock($product->getStock() - $oldDelta);
        $this->applyDelta($product, $newDelta);
    }
}

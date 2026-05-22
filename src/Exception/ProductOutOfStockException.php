<?php

namespace App\Exception;

/**
 * Thrown when a product cannot be added to the cart because inventory is depleted.
 */
final class ProductOutOfStockException extends \InvalidArgumentException
{
    public const MESSAGE = 'This item is currently out of stock.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Product\Exception;

class ProductAlreadySoldException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Product "%s" has already been sold.', $id));
    }
}

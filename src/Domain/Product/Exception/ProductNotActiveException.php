<?php

declare(strict_types=1);

namespace App\Domain\Product\Exception;

class ProductNotActiveException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Product "%s" is not active for purchase.', $id));
    }
}

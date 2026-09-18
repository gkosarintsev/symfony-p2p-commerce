<?php

declare(strict_types=1);

namespace App\Domain\Product\Exception;

class ProductNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Product "%s" was not found.', $id));
    }
}

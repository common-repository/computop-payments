<?php

namespace ComputopSdk\Struct\RequestData\Subtypes;

use ComputopSdk\Struct\Traits\ToArrayTrait;

class Article
{
    use ToArrayTrait;
    public string $name;
    public int $quantity;
    public int $unit_price;
    public int $tax_rate;
    public int $total_amount;
    public int $total_tax_amount;
}

<?php

namespace ComputopSdk\Struct;

use ComputopSdk\Struct\Traits\ToArrayTrait;

abstract class BaseAbstractRequestData
{
    use ToArrayTrait;

    public string $MerchantId;
    public string $MAC;
    public string $TransID;
    public int $Amount = 0;
    public string $Currency = '';
}

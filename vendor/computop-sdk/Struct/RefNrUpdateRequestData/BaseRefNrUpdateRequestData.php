<?php

namespace ComputopSdk\Struct\RefNrUpdateRequestData;

use ComputopSdk\Struct\BaseAbstractRequestData;
use ComputopSdk\Struct\Traits\ToArrayTrait;

class BaseRefNrUpdateRequestData extends BaseAbstractRequestData
{
    use ToArrayTrait;
    public string $RefNr;
}

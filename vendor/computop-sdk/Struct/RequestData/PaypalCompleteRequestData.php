<?php

namespace ComputopSdk\Struct\RequestData;

use ComputopSdk\Struct\BaseAbstractRequestData;
use ComputopSdk\Struct\Traits\ToArrayTrait;

class PaypalCompleteRequestData extends BaseAbstractRequestData
{
    use ToArrayTrait;
    public string $PayID;
    public string $RefNr;
}

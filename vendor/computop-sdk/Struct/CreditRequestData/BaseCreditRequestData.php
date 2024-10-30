<?php

namespace ComputopSdk\Struct\CreditRequestData;

use ComputopSdk\Struct\BaseAbstractRequestData;

class BaseCreditRequestData extends BaseAbstractRequestData
{
    public string $PayID;
    public ?string $RefNr;
    public ?string $OrderDesc;
    public ?string $ReqId = null;
    public ?string $OrderDesc2;
    public ?string $UserData;
    public ?string $Reason;
}

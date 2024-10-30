<?php

namespace ComputopSdk\Struct\CaptureRequestData;

use ComputopSdk\Struct\BaseAbstractRequestData;

abstract class AbstractCaptureRequestData extends BaseAbstractRequestData
{
    public string $PayID;
    public string $Response = 'encrypt';
    public ?string $ReqId = null;
    public ?string $OrderDesc = null;
}

<?php

namespace ComputopSdk\Struct\RequestData;

use ComputopSdk\Struct\BaseAbstractRequestData;

class AbstractRequestData extends BaseAbstractRequestData
{
    public const CAPTURE_MODE_AUTO = 'AUTO';
    public const CAPTURE_MODE_MANUAL = 'MANUAL';
    public string $RefNr;
    public string $OrderDesc;
    public string $UserData;
    public string $URLSuccess;
    public string $URLFailure;
    public string $URLNotify;
    public string $Response = 'encrypt';
    public string $EtiID = '';
    public ?string $ReqId = null;
}

<?php

namespace ComputopSdk\Struct\ResponseData;

class CaptureResponse extends AbstractResponse
{
    public const STATUS_OK = 'OK';
    public const STATUS_FAILED = 'FAILED';

    public string $refnr;
}

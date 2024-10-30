<?php

namespace ComputopSdk\Struct\CaptureRequestData;

class DirectDebitCaptureRequestData extends AbstractCaptureRequestData
{
    public string $MandateID;
    public string $DtOfSgntr;
}

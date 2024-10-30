<?php

namespace ComputopSdk\Struct\RequestData;

class DirectDebitRequestData extends AbstractRequestData
{
    public string $IBAN;
    public ?string $BIC = null;
    public string $AccOwner;
    public string $AccBank;
    public string $MandateID;
    public string $DtOfSgntr;
    public string $Language;
    public string $Capture = AbstractRequestData::CAPTURE_MODE_AUTO;
}

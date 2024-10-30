<?php

namespace ComputopSdk\Struct\RequestData;

class IdealRequestData extends AbstractRequestData
{
    public ?string $IssuerID = null;
    public string $BIC;
    public ?string $Language = null;
}

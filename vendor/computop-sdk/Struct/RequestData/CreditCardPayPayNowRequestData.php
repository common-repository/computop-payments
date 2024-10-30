<?php

namespace ComputopSdk\Struct\RequestData;

class CreditCardPayPayNowRequestData extends AbstractRequestData
{
    public string $Capture = AbstractRequestData::CAPTURE_MODE_AUTO;
    public string $MsgVer = '2.0'; //readonly in later versions
    public ?array $billToCustomer = null;
    public ?array $browserInfo = null;
    public ?string $billingDescriptor = null;
}

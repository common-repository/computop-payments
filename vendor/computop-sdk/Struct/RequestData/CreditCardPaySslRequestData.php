<?php

namespace ComputopSdk\Struct\RequestData;

class CreditCardPaySslRequestData extends AbstractRequestData
{
    public string $Capture = AbstractRequestData::CAPTURE_MODE_AUTO;
    public string $Template = 'ct_responsive';
    public ?array $billToCustomer = null;
    public string $msgver = '2.0';
}

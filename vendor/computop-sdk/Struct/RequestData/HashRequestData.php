<?php

namespace ComputopSdk\Struct\RequestData;

class HashRequestData
{
    public ?string $payId = null;
    public string $transactionId;
    public string $merchantId;
    public ?int $amount = null;
    public ?string $currency = null;
}

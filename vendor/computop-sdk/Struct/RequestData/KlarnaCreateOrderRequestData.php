<?php

namespace ComputopSdk\Struct\RequestData;

use ComputopSdk\Struct\Traits\AddressesTrait;

class KlarnaCreateOrderRequestData extends KlarnaHostedPaymentPageRequestData
{
    public const EVENT_TOKEN_CREATE_ORDER = 'CNO';
    public string $TokenExt;
}

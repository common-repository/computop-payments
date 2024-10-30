<?php

namespace ComputopSdk\Struct\RequestData;

class PaypalRequestData extends AbstractRequestData
{
    public const PAYPAL_METHOD_SHORTCUT = 'shortcut';
    public const PAYPAL_METHOD_MARK = 'Mark';
    public const PAYPAL_TX_TYPE_AUTH = 'Auth';
    public const PAYPAL_TX_TYPE_ORDER = 'Order';
    public ?string $PayPalMethod = null;
    public ?string $TxType = null;
    public ?string $PayID = null;
    public string $Capture = AbstractRequestData::CAPTURE_MODE_AUTO;
}

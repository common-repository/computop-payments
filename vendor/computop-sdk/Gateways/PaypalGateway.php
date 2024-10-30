<?php

namespace ComputopSdk\Gateways;

use ComputopSdk\Struct\Client\ResponseData;
use ComputopSdk\Struct\RequestData\PaypalCompleteRequestData;

class PaypalGateway extends AbstractGateway
{
    public const METHOD = 'ExternalServices/paypalorders.aspx';
    public const METHOD_COMPLETE = 'paypalComplete.aspx';

    public function paypalComplete(PaypalCompleteRequestData $requestData): ResponseData
    {
        return $this->postData($requestData, static::METHOD_COMPLETE);
    }
}

<?php

namespace ComputopSdk\Gateways;

use ComputopSdk\Struct\Client\ResponseData;
use ComputopSdk\Struct\RequestData\GiropayRequestData;
use ComputopSdk\Struct\RequestData\IdealIssuerListRequestData;
use ComputopSdk\Struct\RequestData\IdealRequestData;

class IdealGateway extends AbstractGateway
{
    public const METHOD = 'ideal.aspx';

    public function getIssuerList()
    {
        $requestData = new IdealIssuerListRequestData();
        $requestData->TransID = '';
        $requestData->Currency = '';
        $requestData->Amount = 0;
        $requestData->MerchantId = $this->configuration->merchantId;
        return $this->postData($requestData, 'idealIssuerList.aspx');
    }
}

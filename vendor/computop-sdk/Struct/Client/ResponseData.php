<?php

namespace ComputopSdk\Struct\Client;

use ComputopSdk\Struct\ResponseData\CaptureResponse;
use ComputopSdk\Struct\ResponseData\InquireResponse;
use ComputopSdk\Struct\ResponseData\Response;

class ResponseData
{
    public $requestData = null;
    public ?array $responseArray = null;
    /**
     * @var CaptureResponse|InquireResponse|Response
     */
    public $responseObject = null;
    public ?string $rawResponse = null;
    public ?string $errorMessage = null;
    public ?bool $isSuccess = null;

}

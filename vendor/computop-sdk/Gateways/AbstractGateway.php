<?php

namespace ComputopSdk\Gateways;

use ComputopSdk\Clients\AbstractClient;
use ComputopSdk\Clients\CurlClient;
use ComputopSdk\Services\EncryptionService;
use ComputopSdk\Struct\BaseAbstractRequestData;
use ComputopSdk\Struct\CaptureRequestData\AbstractCaptureRequestData;
use ComputopSdk\Struct\Client\ResponseData;
use ComputopSdk\Struct\Config\Config;
use ComputopSdk\Struct\CreditRequestData\BaseCreditRequestData;
use ComputopSdk\Struct\InquireRequestData\InquireRequestData;
use ComputopSdk\Struct\RefNrUpdateRequestData\BaseRefNrUpdateRequestData;
use ComputopSdk\Struct\RequestData\AbstractRequestData;
use ComputopSdk\Struct\RequestData\HashRequestData;
use ComputopSdk\Struct\ResponseData\CaptureResponse;
use ComputopSdk\Struct\ResponseData\InquireResponse;
use ComputopSdk\Struct\ResponseData\Response;

abstract class AbstractGateway
{
    public const PAYGATE_URL = 'https://www.computop-paygate.com/';
    public const METHOD_CAPTURE = 'capture.aspx';
    public const METHOD_REFUND = 'credit.aspx';
    public const METHOD_REF_NR_UPDATE = 'RefNrChange.aspx';
    public const METHOD_INQUIRE = 'inquire.aspx';

    protected Config $configuration;
    protected EncryptionService $encryptionService;
    protected AbstractClient $client;

    public function __construct(Config $configuration)
    {
        $this->configuration = $configuration;
        $this->encryptionService = new EncryptionService($configuration);
        $this->client = new CurlClient();
    }

    protected function addHashToRequestData(BaseAbstractRequestData $requestData, ?string $payId = null)
    {
        $hashRequestData = new HashRequestData();
        $hashRequestData->payId = ($payId === null && property_exists($requestData, 'PayID') && !empty($requestData->PayID)) ? $requestData->PayID : $payId;
        $hashRequestData->merchantId = $requestData->MerchantId;
        $hashRequestData->transactionId = $requestData->TransID;
        $hashRequestData->currency = $requestData->Currency;
        $hashRequestData->amount = $requestData->Amount;
        $requestData->MAC = $this->encryptionService->calculateHash($hashRequestData);
    }

    protected function glueRequestData(BaseAbstractRequestData $requestData): string
    {
        $data = $requestData->toArray();
        $dataArray = array_map(function ($key, $value) {
            return $key . '=' . $value;
        }, array_keys($data), $data);
        return implode('&', $dataArray);
    }

    public function postData(BaseAbstractRequestData $requestData, string $method = null): ResponseData
    {
        $this->addHashToRequestData($requestData);
        $requestDataToEncrypt = $this->glueRequestData($requestData);

        $publicRequestData = [
            'MerchantID' => $requestData->MerchantId,
            'Len' => strlen($requestDataToEncrypt),
            'Data' => $this->encryptionService->encrypt($requestDataToEncrypt),
        ];

        $response = $this->client->post($method ?? static::METHOD, $publicRequestData);
        $response->requestData['DataUnencrypted'] = $requestDataToEncrypt;
        if (is_array($response->responseArray) && !empty($response->responseArray['Data'])) {
            $decryptedDataString = $this->encryptionService->decrypt($response->responseArray['Data']);
            if ($decryptedDataString) {
                $parts = explode('&', trim($decryptedDataString));
                $decryptedDataArray = [];
                foreach ($parts as $part) {
                    $partParts = explode('=', $part, 2);
                    if (count($partParts) === 2) {
                        $decryptedDataArray[$partParts[0]] = $partParts[1];
                    }
                }
                $response->responseArray['Data'] = $decryptedDataArray;
                if ($requestData instanceof AbstractRequestData) {
                    $response->responseObject = Response::createFromResponseString($decryptedDataString);
                } elseif ($requestData instanceof AbstractCaptureRequestData) {
                    $response->responseObject = CaptureResponse::createFromResponseString($decryptedDataString);
                } elseif ($requestData instanceof InquireRequestData) {
                    $response->responseObject = InquireResponse::createFromResponseString($decryptedDataString);
                }
            }
        }
        return $response;
    }

    public function capture(AbstractCaptureRequestData $requestData): ResponseData
    {
        return $this->postData($requestData, static::METHOD_CAPTURE);
    }

    public function updateRefNr(BaseRefNrUpdateRequestData $requestData): ResponseData
    {
        return $this->postData($requestData, static::METHOD_REF_NR_UPDATE);
    }

    public function refund(BaseCreditRequestData $requestData): ResponseData
    {
        return $this->postData($requestData, static::METHOD_REFUND);
    }

    public function inquire(InquireRequestData $requestData): ResponseData
    {
        return $this->postData($requestData, static::METHOD_INQUIRE);
    }

    public function getEncryptedRequestData(AbstractRequestData $requestData): array
    {
        $this->addHashToRequestData($requestData);
        $requestDataToEncrypt = $this->glueRequestData($requestData);
        return [
            'MerchantID' => $requestData->MerchantId,
            'Len' => strlen($requestDataToEncrypt),
            'Data' => $this->encryptionService->encrypt($requestDataToEncrypt),
        ];
    }

    public function getUrl(AbstractRequestData $requestData): string
    {
        return static::PAYGATE_URL . static::METHOD . '?' . http_build_query($this->getEncryptedRequestData($requestData));
    }

    public function decrypt(string $encryptedData): string
    {
        return $this->encryptionService->decrypt($encryptedData);
    }
}

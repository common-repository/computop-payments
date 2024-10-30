<?php

namespace ComputopSdk\Clients;

use ComputopSdk\Struct\Client\ResponseData;

class CurlClient extends AbstractClient
{
    public function post($method, $data): ResponseData
    {
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            [
                CURLOPT_URL => static::API_URL . $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => 1,
                CURLOPT_NOBODY => false,
                CURLOPT_HTTPHEADER => [
                    "cache-control: no-cache",
                    "content-type: application/x-www-form-urlencoded",
                    "accept: */*",
                    "accept-encoding: gzip, deflate",
                ],
                CURLOPT_POSTFIELDS => http_build_query($data),
            ]
        );
        $rawResponse = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        $parsedResponse = null;
        if (!empty($rawResponse)) {
            //parse query string in array
            parse_str($rawResponse, $parsedResponse);
        }

        $response = new ResponseData();
        $response->responseArray = is_array($parsedResponse) ? $parsedResponse : null;
        $response->rawResponse = $rawResponse ?: null;
        $response->requestData = $data;
        $response->errorMessage = $error ?: null;
        $response->isSuccess = empty($error);
        return $response;
    }

}

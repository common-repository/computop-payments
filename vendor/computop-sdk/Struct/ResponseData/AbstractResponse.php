<?php

namespace ComputopSdk\Struct\ResponseData;

use ComputopSdk\Struct\Traits\ToArrayTrait;

abstract class AbstractResponse{
    use ToArrayTrait;

    /**
     * @param string $responseString
     * @return static
     */
    public static function createFromResponseString(string $responseString)
    {
        $response = new static();
        $response->rawString = $responseString;
        $rawArray = explode('&', $responseString);
        foreach ($rawArray as $rawArrayItem) {
            $rawArrayItem = explode('=', $rawArrayItem, 2);
            $response->rawArray[$rawArrayItem[0]] = $rawArrayItem[1];
            if(property_exists($response, $rawArrayItem[0])) {
                $response->{$rawArrayItem[0]} = $rawArrayItem[1];
            }
        }
        return $response;
    }

    public string $rawString;
    public array $rawArray = [];

    public string $mid;
    public string $PayID;
    public string $TransID;
    public string $Status;
    public string $Code;
    public string $XID;

    public string $MAC;
    public string $errortext = '';
}
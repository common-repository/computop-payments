<?php

namespace ComputopSdk\Struct\ResponseData;

class InquireResponse extends AbstractResponse
{
    public const STATUS_OK = 'OK';
    public const STATUS_FAILED = 'FAILED';

    public ?string $Description = null;
    public ?string $LastStatus = null;
    public $AmountAuth = null; //is an integer transported as string
    public $AmountCap = null; //is an integer transported as string
    public $AmountCred = null; //is an integer transported as string
    public ?string $Currency = null;
}
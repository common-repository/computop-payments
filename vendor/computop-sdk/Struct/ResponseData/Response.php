<?php

namespace ComputopSdk\Struct\ResponseData;

class Response extends AbstractResponse
{
    public const STATUS_OK = 'OK';
    public const STATUS_AUTHORIZE_REQUEST = 'AUTHORIZE_REQUEST';
    public const STATUS_AUTHORIZED = 'AUTHORIZED';
    public const STATUS_PENDING = 'PENDING';

    public string $Description;
}

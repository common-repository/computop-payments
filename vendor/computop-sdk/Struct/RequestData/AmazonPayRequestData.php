<?php

namespace ComputopSdk\Struct\RequestData;

use ComputopSdk\Struct\Traits\AddressesTrait;

class AmazonPayRequestData extends AbstractRequestData
{

    use AddressesTrait;

    public string $CountryCode = 'EU';
    public string $checkoutMode = 'ProcessOrder';
    public string $ShopUrl;
    public ?string $Name = null;
    public ?string $SDZipcode = null;
    public ?string $sdPhone = null;
    public ?string $URLCancel = null;
}

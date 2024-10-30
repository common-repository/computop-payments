<?php

namespace ComputopSdk\Struct\RequestData;

use ComputopSdk\Struct\Traits\AddressesTrait;

class EasyCreditRequestData extends AbstractRequestData
{
    use AddressesTrait;

    public const EVENT_TOKEN_INT = 'INT';
    public const EVENT_TOKEN_GET = 'GET';
    public const EVENT_TOKEN_CON = 'CON';

    public const CUSTOMER_LOGGED_IN_TRUE = 'YES';
    public const CUSTOMER_LOGGED_IN_FALSE = 'NO';

    public const SALUTATION_MR = 'MR';
    public const SALUTATION_MRS = 'MRS';
    public const SALUTATION_DIVERSE = 'DIVERS';

    public string $version = 'v3';

    public ?string $Salutation = null;
    public ?string $bdStreetNr = null;
    public ?string $sdStreetNr = null;

    public ?string $Email = null;
    public ?string $MobileNr = null;
    public string $EventToken;
    public ?string $CustomerSince = null;
    public ?string $CustomerLoggedIn = null;
    public ?int $NumberArticles = null;
    public ?int $NumberOrders = null;

    public ?string $PayID = null;
    public ?string $Capture = 'MANUAL';
}

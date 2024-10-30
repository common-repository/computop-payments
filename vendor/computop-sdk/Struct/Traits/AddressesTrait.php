<?php

namespace ComputopSdk\Struct\Traits;


trait AddressesTrait
{
    public string $bdCountryCode;
    public ?string $bdFirstName = null;
    public ?string $bdLastName = null;
    public ?string $bdStreet = null;
    public ?string $bdZip = null;
    public ?string $bdCity = null;

    public string $sdCountryCode;
    public ?string $sdFirstName = null;
    public ?string $sdLastName = null;
    public ?string $sdStreet = null;
    public ?string $sdZip = null;
    public ?string $sdCity = null;
}

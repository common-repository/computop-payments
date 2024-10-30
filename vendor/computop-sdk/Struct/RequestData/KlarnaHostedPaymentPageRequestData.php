<?php

namespace ComputopSdk\Struct\RequestData;

use ComputopSdk\Struct\Traits\AddressesTrait;

class KlarnaHostedPaymentPageRequestData extends AbstractRequestData
{
    use AddressesTrait;

    public ?string $bdCompany = null;
    public ?string $bdEmail = null;

    public ?string $sdCompany = null;
    public ?string $sdEmail = null;

    public int $TaxAmount;
    public array $ArticleList;

    public string $Language;
    public string $Account = '0';
    public ?string $PayID = null;
    public ?string $EventToken = null;

    public function setLanguage(string $Language): self
    {
        $allowedLanguages = ['DE', 'DK', 'FI', 'SE', 'NO', 'NL', 'FR', 'IT', 'EN', 'ES', 'CA', 'PL'];
        if (!in_array($Language, $allowedLanguages)) {
            $Language = 'EN';
        }
        $this->Language = $Language;
        return $this;
    }
}

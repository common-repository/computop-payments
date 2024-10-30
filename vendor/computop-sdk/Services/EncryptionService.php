<?php

namespace ComputopSdk\Services;

use ComputopSdk\Exceptions\EncryptionException;
use ComputopSdk\Struct\Config\Config;
use ComputopSdk\Struct\RequestData\HashRequestData;
use phpseclib3\Crypt\Blowfish;

class EncryptionService
{
    public const ALGORITHM = 'BF-ECB';
    protected Config $config;


    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @throws EncryptionException
     */
    public function encrypt($string): string
    {
        $blockSize = 8;
        $len = strlen($string);
        $paddingLen = intval(($len + $blockSize - 1) / $blockSize) * $blockSize - $len;
        $padding = str_repeat("\0", $paddingLen);
        $data = $string . $padding;

        $blowfish = new Blowfish('ecb');
        $blowfish->disablePadding();
        //$blowfish->setKey($this->getBlowfishKey($this->config->encryptionKey));
        $blowfish->setKey($this->config->encryptionKey);

        $encrypted = $blowfish->encrypt($data);
        /*
        $encrypted = openssl_encrypt(
            $data,
            static::ALGORITHM,
            $this->getBlowfishKey($this->config->encryptionKey),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );
        */

        if ($encrypted === false) {
            throw new EncryptionException(openssl_error_string());
        }

        return bin2hex($encrypted);
    }

    /**
     * @throws EncryptionException
     */
    public function decrypt($hex): string
    {
        $blowfish = new Blowfish('ecb');
        $blowfish->disablePadding();
        $blowfish->setKey($this->config->encryptionKey);
        //$blowfish->setKey($this->getBlowfishKey($this->config->encryptionKey));
        try {
            $decrypted = $blowfish->decrypt(hex2bin($hex));
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        /*
        $decrypted = openssl_decrypt(
            hex2bin($hex),
            static::ALGORITHM,
            $this->getBlowfishKey($this->config->encryptionKey),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );
        */

        if ($decrypted === false) {
            throw new EncryptionException(openssl_error_string());
        }

        return rtrim($decrypted, "\0");
    }

    protected function getBlowfishKey(string $key): string
    {
        if (empty($key)) {
            return $key;
        }
        $len = 72;
        while (strlen($key) < $len) {
            $key .= $key;
        }
        return substr($key, 0, $len);
    }

    public function calculateHash(HashRequestData $hashRequestData)
    {
        $string =
            ($hashRequestData->payId ?? '') .
            '*' .
            $hashRequestData->transactionId .
            '*' .
            $hashRequestData->merchantId .
            '*' .
            ($hashRequestData->amount ?? '') .
            '*' .
            ($hashRequestData->currency ?? '');
        return hash_hmac('sha256', $string, $this->config->hashKey);
    }
}

<?php

namespace ComputopSdk\Struct\Traits;

use ComputopSdk\Struct\RequestData\Subtypes\Article;

trait ToArrayTrait
{
    //convert to constant in future versions (>=PHP8.2)
    public $jsonBase64Properties = [
        'billToCustomer',
        'browserInfo'
    ];


    public function toArray(): array
    {
        $array = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($key === 'jsonBase64Properties') {
                continue;
            }
            if ($key === 'ArticleList' && is_array($value)) {
                $listArray = [];
                /** @var Article $article */
                foreach ($value as $article) {
                    $listArray[] = $article->toArray();
                }
                $toEncode = [
                    'order_lines' => $listArray,
                ];
                $array[$key] = base64_encode(json_encode($toEncode));
            } elseif (in_array($key, $this->jsonBase64Properties) && is_array($value)) {
                $array[$key] = base64_encode(json_encode($value));
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                $array[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $array[$key] = [];
                foreach ($value as $key2 => $value2) {
                    if (is_object($value2) && method_exists($value2, 'toArray')) {
                        $array[$key][$key2] = $value2->toArray();
                    } else {
                        $array[$key][$key2] = $value2;
                    }
                }
            } elseif ($value !== null) {
                //workaround for easyCredit
                switch ($key) {
                    case 'bdFirstName':
                        $array['FirstName'] = $value;
                        break;
                    case 'bdLastName':
                        $array['LastName'] = $value;
                        break;
                }
                $array[$key] = $value;
            }
        }
        return $array;
    }
}

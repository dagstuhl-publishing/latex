<?php

namespace Dagstuhl\Latex\Bibliography;

class Reference
{
    public readonly mixed $internalData;
    public readonly int $order;
    public readonly string $text;
    public readonly ?string $url;
    public readonly ?string $urlType;
    public readonly string $bibKey;

    public function __construct(int $order, string $text, ?string $url, ?string $urlType, string $bibKey, mixed $internalData = null) {
        $this->order = $order;
        $this->text = $text;
        $this->url = $url;
        $this->urlType = $urlType;
        $this->bibKey = $bibKey;
        $this->internalData = $internalData;
    }

    public static function fromArray(array $referenceArray): Reference
    {
        return new self((int)$referenceArray['order'], $referenceArray['text'], $referenceArray['url'], $referenceArray['urlType'], $referenceArray['bibKey']);
    }

    public function toArray($addInternalData = false) : array {
        $array = [
            'order' => $this->order,
            'text' => $this->text,
            'url' => $this->url,
            'urlType' => $this->urlType,
            'bibKey' => $this->bibKey,
        ];

        if ($addInternalData) {
            $array['internalData'] = $this->internalData;
        }

        return $array;
    }
}
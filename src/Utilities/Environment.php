<?php

namespace Dagstuhl\Latex\Utilities;

class Environment
{
    public static function toString(array $env): string
    {
        $envString = '';
        foreach($env as $key=>$value) {
            $envString .= ' ' . $key . '=' . escapeshellarg($value);
        }

        return trim($envString);
    }
}
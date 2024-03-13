<?php

namespace Dagstuhl\Latex\Styles;

interface StyleDescription
{
    public static function getMetadataItems();

    public static function getPackages($classification, $styleName);

    public static function getPath($styleName);

    public static function getFiles($styleName);
}
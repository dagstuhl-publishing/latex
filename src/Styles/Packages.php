<?php

namespace Dagstuhl\Latex\Styles;

class Packages
{
    const CLASSIFIED_FORBIDDEN = 'forbidden';
    const CLASSIFIED_PRE_LOADED = 'pre-loaded';
    const CLASSIFIED_WARNING_ON_UPLOAD = 'warning-on-upload';
    const CLASSIFIED_NEEDS_INTERNAL_REVISION = 'needs-internal-revision';

    // override in own implementation
    public static function getPackages($classification, $styleName)
    {
        return [];
    }
}
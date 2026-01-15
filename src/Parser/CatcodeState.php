<?php

namespace Dagstuhl\Latex\Parser;

/**
 * Tracks the internal state of \catcode command resolution.
 */
enum CatcodeState
{
    case IDLE;
    case EXPECTING_TARGET;
    case EXPECTING_VALUE_OR_EQUALS;
    case EXPECTING_VALUE;
}

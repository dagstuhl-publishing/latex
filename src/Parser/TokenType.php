<?php

namespace Dagstuhl\Latex\Parser;

/**
 * Defines the types of tokens produced by the Lexer.
 * Categorized based on TeX catcodes and LaTeX structural requirements.
 */
enum TokenType
{
    case COMMAND;      // \command or active character (Cat 0, 13)
    case GROUP_OPEN;   // { (Cat 1)
    case GROUP_CLOSE;  // } (Cat 2)
    case MATH_TOGGLE;   // $ or $$ (Cat 3)
    case ALIGN_TAB;    // & (Cat 4)
    case OPT_OPEN;     // [ (Explicitly handled for optional args)
    case OPT_CLOSE;    // ] (Explicitly handled for optional args)
    case COMMENT;      // % (Cat 14)
    case TEXT;         // Character data (Cat 11, 12, 7, 8)
    case WHITESPACE;   // Space, Tab, LF (Cat 10)
}

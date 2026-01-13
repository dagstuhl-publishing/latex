<?php

namespace Dagstuhl\Latex\Parser;

// Manual loading of the required files
require_once __DIR__ . '/Lexer.php';
require_once __DIR__ . '/ParseTreeNode.php';
require_once __DIR__ . '/ParseException.php';
require_once __DIR__ . '/LatexParser.php';
require_once __DIR__ . '/Token.php';
require_once __DIR__ . '/TreeNodes/EnvelopeNode.php';
require_once __DIR__ . '/TreeNodes/RootNode.php';
require_once __DIR__ . '/TreeNodes/TextNode.php';
require_once __DIR__ . '/TreeNodes/WhitespaceNode.php';
require_once __DIR__ . '/TreeNodes/MathNode.php';
require_once __DIR__ . '/TreeNodes/EnvironmentNode.php';
require_once __DIR__ . '/TreeNodes/MathEnvironmentNode.php';
require_once __DIR__ . '/TreeNodes/CommandNode.php';
require_once __DIR__ . '/TreeNodes/ArgumentNode.php';
require_once __DIR__ . '/TreeNodes/GroupNode.php';
require_once __DIR__ . '/TreeNodes/CommentNode.php';
require_once __DIR__ . '/TreeNodes/VerbNode.php';

use Dagstuhl\Latex\Parser\TreeNodes\TextNode;
use Dagstuhl\Latex\Parser\TreeNodes\CommandNode;
use Dagstuhl\Latex\Parser\TreeNodes\ArgumentNode;
use Dagstuhl\Latex\Parser\TreeNodes\GroupNode;
use Dagstuhl\Latex\Parser\TreeNodes\CommentNode;
use Dagstuhl\Latex\Parser\TreeNodes\VerbNode;
use Dagstuhl\Latex\Parser\TreeNodes\EnvironmentNode;
use Dagstuhl\Latex\Parser\TreeNodes\MathEnvironmentNode;

/**
 * --- Updated Diagnostic Suite ---
 */

function print_doc_diff(string $original, string $serialized): void {
    $origLines = explode("\n", $original);
    $serLines = explode("\n", $serialized);

    echo "\n--- ORIGINAL DOCUMENT ---\n" . $original . "\n";
    echo "\n--- SERIALIZED DOCUMENT ---\n";

    $mismatchFound = false;
    $max = max(count($origLines), count($serLines));

    for ($i = 0; $i < $max; $i++) {
        $sLine = $serLines[$i] ?? "[END OF DOCUMENT]";
        $oLine = $origLines[$i] ?? "[END OF DOCUMENT]";

        echo $sLine . "\n";

        if (!$mismatchFound && $sLine !== $oLine) {
            $charIndex = 0;
            $limit = min(strlen($sLine), strlen($oLine));
            while ($charIndex < $limit && $sLine[$charIndex] === $oLine[$charIndex]) {
                $charIndex++;
            }

            echo str_repeat(" ", $charIndex) . "^ <--- MISMATCH AT COLUMN " . ($charIndex + 1) . "\n";
            $mismatchFound = true;
        }
    }
}

class SimpleTester {
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $fn): void {
        try {
            $fn();
            echo "✅ PASS: $name\n";
            $this->passed++;
        } catch (\Throwable $e) {
            echo "❌ FAIL: $name\n    --> " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }

    public function summary(): void {
        echo "\n" . str_repeat("=", 30) . "\n";
        echo "Tests: {$this->passed} Passed, {$this->failed} Failed\n";
        exit($this->failed > 0 ? 1 : 0);
    }
}

$t = new SimpleTester();
$parser = new LatexParser();

// --- Test Cases ---

$t->test("Catcode: Standard numeric (64 11)", function () use ($parser) {
    $source = "\\catcode 64 11";
    $root = $parser->parse($source);
    if ($root->toLatex() !== $source) throw new ParseException("Round-trip failed", 1, $root);
});

$t->test("Catcode: Optional equals (64 = 11)", function () use ($parser) {
    $source = "\\catcode 64 = 11";
    $root = $parser->parse($source);
    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Round-trip failed with equals sign", 1, $root);
    }
});

$t->test("Catcode: Backtick notation (`\\@ = 11)", function () use ($parser) {
    $source = "\\catcode`\\@ = 11";
    $root = $parser->parse($source);

    $checkSource = "@asLetter";
    $checkRoot = $parser->parse($checkSource);
    $nodes = $checkRoot->getChildren();

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Round-trip failed with backtick notation", 1, $root);
    }
});

$t->test("Catcode: Mixed whitespace and comments", function () use ($parser) {
    $source = "\\catcode % comment\n 64\n =\n 11";
    $root = $parser->parse($source);
    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Round-trip failed with comments/whitespace", 1, $root);
    }
});

$t->test("Structural: Optional arguments are children and brackets are consumed", function () use ($parser) {
    $source = "\\usepackage[utf8]{inputenc}";
    $root = $parser->parse($source);

    $children = $root->getChildren();
    $cmd = $children[0];
    if (!($cmd instanceof CommandNode)) throw new ParseException("Expected CommandNode, but got " . get_class($cmd), 1, $root);

    $cmdChildren = $cmd->getChildren();
    if (count($cmdChildren) !== 2) {
        throw new ParseException("Expected 2 arguments, found " . count($cmdChildren), 1, $root);
    }

    $optArg = $cmdChildren[0];
    if (!($optArg instanceof ArgumentNode) || !$optArg->isOptional) {
        throw new ParseException("First child should be an optional ArgumentNode", 1, $root);
    }

    foreach ($children as $node) {
        if ($node instanceof TextNode && str_contains($node->toLatex(), "]")) {
            throw new ParseException("Stray ']' found in tree as TextNode!", 1, $root);
        }
    }
});

$t->test("Structural: Nested groups in optional arguments", function () use ($parser) {
    $source = "\\mycmd[{value}]{content}";
    $root = $parser->parse($source);

    $children = $root->getChildren();
    $cmd = $children[0];

    $optArg = $cmd->getChildren()[0];

    $innerGroup = $optArg->getChildren()[0];

    if (!$innerGroup instanceof GroupNode) {
        throw new ParseException("Expected GroupNode for nested braces, but got " . get_class($innerGroup), 1, $root);
    }

    if ($innerGroup->toLatex() !== "{value}") {
        throw new ParseException("Nested group content mismatch: " . $innerGroup->toLatex(), 1, $root);
    }
});

$t->test("Encoding: Byte vs Character handling for €", function () use ($parser) {
    $euro = "€";

    $lexerAscii = new \Dagstuhl\Latex\Parser\Lexer($euro);
    $token1 = $lexerAscii->next();

    if (($token1->end - $token1->start) !== 3) {
        throw new \Exception("ASCII Lexer should see 3 bytes for €");
    }

    $sourceUtf8 = "\\usepackage[utf8]{inputenc}€";
    $root = $parser->parse($sourceUtf8);

    $euroNode = null;
    foreach ($root->getChildren() as $node) {
        if ($node instanceof CommandNode || $node instanceof TextNode) {
            if (str_contains($node->toLatex(), "€")) {
                $euroNode = $node;
                break;
            }
        }
    }

    if (!$euroNode) {
        throw new ParseException("Euro symbol not found in AST", 1, $root);
    }

    $output = $euroNode->toLatex();
    if (strlen($output) !== 3) {
        throw new ParseException("Euro symbol in output must preserve all 3 bytes", 1, $root);
    }

    if (mb_strlen($output, 'UTF-8') !== 1) {
        throw new ParseException("Euro symbol should be interpreted as 1 character in UTF-8 mode", 1, $root);
    }
});

$t->test("Lexer: Multi-byte UTF-8", function() {
    $source = "München€";
    $lexer = new Lexer($source);
    $token = $lexer->next();
    $val = substr($source, $token->start, $token->end - $token->start);
    if ($val !== "München€") throw new \Exception("UTF-8 encoding failure: expected '" . $source . "', but got '" . $val . "'.");
});

$t->test("Lexer: Deep Byte-Accuracy & Line Tracking", function() {
    $source = "A\n§€\tB";
    $lexer = new Lexer($source);
    $lexer->setEncoding('utf-8');

    $expected = [
        ['char' => 'A',  'line' => 1, 'bytes' => 1],
        ['char' => "\n", 'line' => 1, 'bytes' => 1],
        ['char' => '§',  'line' => 2, 'bytes' => 2],
        ['char' => '€',  'line' => 2, 'bytes' => 3],
        ['char' => "\t", 'line' => 2, 'bytes' => 1],
        ['char' => 'B',  'line' => 2, 'bytes' => 1],
    ];

    foreach ($expected as $i => $exp) {
        $charObj = $lexer->getChar();
        $actualChar = substr($source, $charObj->charStart, $charObj->charEnd - $charObj->charStart);
        $actualBytes = strlen($actualChar);

        if ($actualBytes !== $exp['bytes']) {
            throw new \Exception("Step $i: Byte length mismatch for '{$actualChar}'. Expected {$exp['bytes']}, got $actualBytes");
        }

        if ($actualChar !== $exp['char']) {
            throw new \Exception("Step $i: Char mismatch. Expected '{$exp['char']}', got '$actualChar'");
        }

        if ($charObj->lineNumber !== $exp['line']) {
            throw new \Exception("Step $i: Line mismatch for '{$actualChar}'. Expected {$exp['line']}, got {$charObj->lineNumber}");
        }
    }

    if ($lexer->consumeRawChar()[0] !== null) {
        throw new \Exception("Lexer should be at EOF");
    }
});

$t->test("Parser: Nested Groups", function() use ($parser) {
    $source = "Outer {Middle {Inner}}";
    $root = $parser->parse($source);
    if ($root->toLatex() !== $source) throw new ParseException("Nesting round-trip failed", 1, $root);
});

$t->test("Parser: Optional & Mandatory", function() use ($parser) {
    $source = "\\cite[p. 10]{Knuth}";
    $root = $parser->parse($source);
    $output = $root->toLatex();
    if ($output !== $source) {
        print_doc_diff($source, $output);
        throw new ParseException("Mixed arguments failed", 1, $root);
    }
});

$t->test("Parser Error: Mismatched Delimiters", function() use ($parser) {
    try {
        $parser->parse("\\textit{wrong]");
        throw new \Exception("Should have thrown ParseException for { ]");
    } catch (ParseException $e) {
        // Success
    }
});

$t->test("Encoding: Multi-package list with spaces", function () use ($parser) {
    $source = "\\usepackage[utf8]{ amsmath, inputenc, graphicx }";
    $root = $parser->parse($source);
    if ($root->toLatex() !== $source) throw new ParseException("Round-trip failed", 1, $root);
});

$t->test("Encoding: Unsupported encoding throws ParseException", function () use ($parser) {
    $source = "\\usepackage[random-enc]{inputenc}";
    try {
        $parser->parse($source);
    } catch (ParseException $e) {
        if (str_contains($e->getMessage(), "Unsupported encoding: random-enc")) {
            return;
        }
        throw new \Exception("Wrong exception message: " . $e->getMessage());
    }
    throw new \Exception("Did not throw ParseException for unsupported encoding");
});

$t->test("Encoding: Comment inside package name", function () use ($parser) {
    $source = "\\usepackage{input%comment\nenc}";
    $root = $parser->parse($source);
    if ($root->toLatex() !== $source) throw new ParseException("Round-trip failed", 1, $root);
});

$t->test("Verbatim: \\verb with custom delimiter", function () use ($parser) {
    $source = "\\verb+raw % text+";
    $root = $parser->parse($source);

    $verbNode = $root->getChildren()[0];
    if (!($verbNode instanceof VerbNode)) {
        throw new ParseException("Expected VerbNode, got " . get_class($verbNode), 1, $root);
    }

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Round-trip failed for \\verb", 1, $root);
    }
});

$t->test("Verbatim: Line counting after multi-line \\verb", function () use ($parser) {
    $source = "Line 1\n\\verb|first line\nsecond line| \nLast Line\section";
    $root = $parser->parse($source);

    if ($root->toLatex() !== $source) {
        throw new ParseException("Round-trip failed", 1, $root);
    }

    $lastNode = $root->getChild(-1);
    if ($lastNode->lineNumber !== 4) {
        throw new ParseException("Line counter mismatch. Expected 4, got " . $lastNode->lineNumber, 1, $root);
    }
});

$t->test("Structural: Command is parent of Argument", function () use ($parser) {
    $source = "\\usepackage{inputenc}";
    $root = $parser->parse($source);

    $cmd = $root->getChildren()[0];
    $arg = $cmd->getChildren()[0];

    if ($arg->parent !== $cmd) {
        throw new ParseException("ArgumentNode's parent should be the CommandNode.", 1, $root);
    }
});

$t->test("Structural: Parent reference check", function () use ($parser) {
    $source = "\\section{Intro}";
    $root = $parser->parse($source);

    $sectionNode = $root->getChildren()[0];
    $argNode = $sectionNode->getChildren()[0];

    if ($sectionNode->parent !== $root) {
        throw new ParseException("CommandNode parent reference is missing", 1, $root);
    }
    if ($argNode->parent !== $sectionNode) {
        throw new ParseException("ArgumentNode parent reference is missing", 1, $root);
    }
});

$t->test("Parser: Escaped Structural Characters", function() use ($parser) {
    $source1 = "100\% certain that \\\\ works with \{ braces \}";
    $source2 = " % but also with comments.";
    $fullSource = $source1 . $source2;
    $root = $parser->parse($fullSource);

    if ($root->toLatex() !== $fullSource) {
        throw new ParseException("Escape round-trip failed! Got: " . $root->toLatex(), 1, $root);
    }

    $search = function($node) use (&$search) {
        if ($node instanceof ArgumentNode) return true;
        foreach ($node->getChildren() as $child) {
            if ($search($child)) return true;
        }
        return false;
    };

    $lastNode = $root->getChild(-1);
    if (!($lastNode instanceof CommentNode)) {
        throw new ParseException("Comment check failed: Last node is not a CommentNode.", 1, $root);
    }

    $commentCount = 0;
    foreach ($root->getChildren() as $node) {
        if ($node instanceof CommentNode) $commentCount++;
    }

    if ($commentCount !== 1) {
        throw new ParseException("Comment count failed: Expected 1, found " . $commentCount, 1, $root);
    }

    if ($search($root)) {
        throw new ParseException("Structural failure: Escaped braces created an ArgumentNode!", 1, $root);
    }
});

$t->test("Structural: Implicit close of command (\section)", function () use ($parser) {
    $source = "\\section{Introduction} This is text.";
    $root = $parser->parse($source);

    $children = $root->getChildren();
    if (count($children) !== 2) {
        throw new ParseException("Root should have 2 children (Command and Text), found " . count($children), 1, $root);
    }

    $section = $children[0];
    if (!$section instanceof CommandNode || $section->name !== '\\section') {
        throw new ParseException("First child should be the \\section command", 1, $root);
    }

    if (count($section->getChildren()) !== 1) {
        throw new ParseException("\\section should have exactly 1 child (the argument)", 1, $root);
    }

    $text = $children[1];
    if (!$text instanceof TextNode || !str_contains($text->content, "This is text")) {
        throw new ParseException("Second child should be the trailing text", 1, $root);
    }
});

$t->test("Environment: Standard round-trip (center)", function () use ($parser) {
    $source = "\\begin{center}\nContent with \\textbf{formatting}\n\\end{center}";
    $root = $parser->parse($source);

    $env = $root->getChildren()[0];
    if (!($env instanceof EnvironmentNode)) {
        throw new ParseException("Expected EnvironmentNode, got " . get_class($env), 1, $root);
    }

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Standard environment round-trip failed", 1, $root);
    }
});

$t->test("Environment: Verbatim raw consumption", function () use ($parser) {
    $source = "\\begin{verbatim}\n% not a comment\n\\textbf{not bold}\n\\end{verbatim}";
    $root = $parser->parse($source);

    $env = $root->getChildren()[0];
    foreach ($env->getChildren() as $child) {
        if (!($child instanceof TextNode)) {
            throw new ParseException("Verbatim body contains non-text node: " . get_class($child), 1, $root);
        }
    }

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Verbatim environment round-trip failed", 1, $root);
    }
});

$t->test("Environment: Nested stack unwinding", function () use ($parser) {
    $source = "\\begin{outer}\n  Text\n  \\begin{inner}\n    Inside\n  \\end{inner}\n\\end{outer}";
    $root = $parser->parse($source);

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Nested environment round-trip failed", 1, $root);
    }

    $outer = $root->getChildren()[0];
    $hasInner = false;
    foreach ($outer->getChildren() as $child) {
        if ($child instanceof EnvironmentNode && $child->envName === 'inner') {
            $hasInner = true;
            break;
        }
    }

    if (!$hasInner) throw new ParseException("Inner environment not found inside outer environment children", 1, $root);
});

$t->test("Environment: Line counting after raw body", function () use ($parser) {
    $source = "\\begin{verbatim}\nLine 2\nLine 3\n\\end{verbatim}\n\section";
    $root = $parser->parse($source);

    $lastNode = $root->getChild(-1);

    if ($lastNode->lineNumber !== 5) {
        throw new ParseException("Line counter mismatch after verbatim. Expected 5, got " . $lastNode->lineNumber, 1, $root);
    }
});

$t->test("Environment: Safety check for optional arguments", function () use ($parser) {
    $source = "\\begin[options]{verbatim}\nRaw\n\\end{verbatim}";
    $root = $parser->parse($source);

    $env = $root->getChildren()[0];
    if ($env->envName !== 'verbatim') {
        throw new ParseException("Failed to identify verbatim environment with optional argument", 1, $root);
    }

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Environment with optional argument round-trip failed", 1, $root);
    }
});

$t->test("Math: Inline shift (\$...\$)", function () use ($parser) {
    $source = "Equation: \$a + b = c\$ done.";
    $root = $parser->parse($source);

    // The Lexer should return MATH_SHIFT for '$'
    // The Parser should ideally wrap these into a MathNode (if implemented)
    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Inline math round-trip failed", 1, $root);
    }
});

$t->test("Math: Display shift (\$\$...\$\$)", function () use ($parser) {
    $source = "Display: \$\$E=mc^2\$\$";
    $root = $parser->parse($source);

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Display math round-trip failed", 1, $root);
    }

    // Verify the Lexer didn't leave a stray '$' behind
    $tokens = [];
    $lexer = new Lexer($source);
    while ($tok = $lexer->next()) $tokens[] = $tok;

    // We expect: TEXT('Display: '), MATH_SHIFT('$$'), TEXT('E=mc^2'), MATH_SHIFT('$$')
    $mathTokens = array_filter($tokens, fn($t) => $t->type === TokenType::MATH_TOGGLE);
    foreach ($mathTokens as $mt) {
        $len = $mt->end - $mt->start;
        if ($len !== 2) throw new \Exception("Display math token should be 2 bytes wide, got $len");
    }
});

$t->test("Math: Multi-line display math line tracking", function () use ($parser) {
    $source = "Line 1\n\$\$\na+b\n\$\$\n\section in Line 5";
    $root = $parser->parse($source);

    if ($root->toLatex() !== $source) {
        throw new ParseException("Multi-line math round-trip failed", 1, $root);
    }

    $lastNode = $root->getChild(-1); // "Line 5"
    if ($lastNode->lineNumber !== 5) {
        throw new ParseException("Line tracking failed after multi-line math. Expected 5, got " . $lastNode->lineNumber, 1, $root);
    }
});

$t->test("Math: Escaped dollar sign should NOT be math", function () use ($parser) {
    $source = "Costs \\\$10.00 total.";
    $root = $parser->parse($source);

    // Searching for any MATH_SHIFT tokens (which shouldn't exist here)
    $output = $root->toLatex();
    if (str_contains($output, '$') && !str_contains($output, '\$')) {
        throw new ParseException("Escaped dollar sign was treated as math shift!", 1, $root);
    }
});

$t->test("Lexer: Active character (tilde ~)", function () use ($parser) {
    $source = "Equation~\ref{eq1}";
    $root = $parser->parse($source);

    // Verify the tilde is captured as its own node/token
    $children = $root->getChildren();

    // We expect: TextNode('Equation'), CommandNode('~'), CommandNode('\ref'), GroupNode('{eq1}')
    $tildeNode = null;
    foreach ($children as $node) {
        if ($node instanceof CommandNode && $node->name === '~') {
            $tildeNode = $node;
            break;
        }
    }

    if (!$tildeNode) {
        throw new ParseException("Active character '~' should be a CommandNode", 1, $root);
    }

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Active character round-trip failed", 1, $root);
    }
});

$t->test("Catcode: Change char to Active (Catcode 13)", function () use ($parser) {
    $source = "\\catcode 33 = 13 !";
    $root = $parser->parse($source);

    // Check the last child of the root
    $lastNode = $root->getChild(-1);
    if (!($lastNode instanceof CommandNode)) {
        throw new \Exception("Character '!' with Catcode 13 should be a CommandNode");
    }
});

$t->test("Math: LaTeX envelopes \( and \[", function () use ($parser) {
    $source = "Inline \(a+b\) and display \[c+d\]";
    $root = $parser->parse($source);

    if ($root->toLatex() !== $source) {
        throw new ParseException("Math envelope round-trip failed", 1, $root);
    }
});

$t->test("Math: Multi-line \[ envelope", function () use ($parser) {
    $source = "Start\n\[\nx^2\n\]\n\section End";
    $root = $parser->parse($source);

    $lastNode = $root->getChild(-1); // "End"
    if ($lastNode->lineNumber !== 5) {
        throw new ParseException("Line tracking failed in \[ envelope. Expected 5, got " . $lastNode->lineNumber, 5);
    }
});

// Test for the amsmath distinction
$t->test("Parser: Math vs Text Environments", function () use ($parser) {
    $source = "\\begin{equation} a=1 \\end{equation} \\begin{split} b=2 \\end{split}";
    $root = $parser->parse($source);

    $eq = $root->getChild(0);
    $split = $root->getChild(1);

    if (!($eq instanceof MathEnvironmentNode)) {
        throw new \Exception("equation should be a MathEnvironmentNode");
    }

    if ($split instanceof MathEnvironmentNode) {
        throw new \Exception("split should NOT be a MathEnvironmentNode");
    }
});

$t->test("Parser: Comprehensive Math Environments", function () use ($parser) {
    $source = <<<'LATEX'
Text mode.
\begin{equation}
  E = mc^2
\end{equation}
\begin{alignat*}{2}
  f(x) &= x^2 \\
  g(x) &= \sqrt{x}
\end{alignat*}
\begin{dcases}
  1 & x > 0 \\
  0 & x \le 0
\end{dcases}
\begin{split}
  a = b
\end{split}
LATEX;

    $root = $parser->parse($source);

    // 1. equation (Standard LaTeX)
    $equation = $root->getChild(1);
    if (!($equation instanceof MathEnvironmentNode)) {
        throw new \Exception("equation should be a MathEnvironmentNode");
    }

    // 2. alignat* (amsmath, has mandatory argument {2})
    $alignat = $root->getChild(3);
    if (!($alignat instanceof MathEnvironmentNode)) {
        throw new \Exception("alignat* should be a MathEnvironmentNode");
    }
    if ($alignat->envName !== 'alignat*') {
        throw new \Exception("Expected envName 'alignat*', got " . $alignat->envName);
    }

    // 3. dcases (mathtools)
    $dcases = $root->getChild(5);
    if (!($dcases instanceof MathEnvironmentNode)) {
        throw new \Exception("dcases should be a MathEnvironmentNode");
    }

    // 4. split (Internal environment - should NOT be MathEnvironmentNode)
    $split = $root->getChild(7);
    if ($split instanceof MathEnvironmentNode) {
        throw new \Exception("split should be a standard EnvironmentNode, not MathEnvironmentNode");
    }
    if (!($split instanceof EnvironmentNode)) {
        throw new \Exception("split should be an EnvironmentNode");
    }
});

$t->test("Structural: Nested command in optional argument", function () use ($parser) {
    $source = "\\textbf[\\textbf{}]";
    $root = $parser->parse($source);

    if ($root->toLatex() !== $source)
    {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Round-trip failed for nested command in optional argument", 1, $root);
    }

    $children = $root->getChildren();
    $outerCmd = $children[0];

    if (!($outerCmd instanceof CommandNode) || $outerCmd->name !== '\\textbf')
    {
        throw new ParseException("Expected outer \\textbf command", 1, $root);
    }

    $outerChildren = $outerCmd->getChildren();
    // We expect one child: the optional ArgumentNode.
    // (Note: If your parser logic implies a mandatory one follows, adjust count accordingly)
    $optArg = $outerChildren[0];

    if (!($optArg instanceof ArgumentNode) || !$optArg->isOptional)
    {
        throw new ParseException("First child of outer \\textbf must be an optional ArgumentNode", 1, $root);
    }

    $innerCmd = $optArg->getChildren()[0];
    if (!($innerCmd instanceof CommandNode) || $innerCmd->name !== '\\textbf')
    {
        throw new ParseException("Optional argument should contain an inner \\textbf CommandNode", 1, $root);
    }
});

$t->test("Structural: Comment with newline preservation", function () use ($parser) {
    // Note: The newline after the comment is essential.
    // In LaTeX, '% comment\n' usually behaves like a single space if followed by more text,
    // but for round-tripping, we need the exact character sequence.
    $source = "% comment\n";
    $root = $parser->parse($source);

    $comment = $root->getChild(0);
    if (!($comment instanceof CommentNode)) {
        throw new ParseException("Expected CommentNode as the first child of the root.", 1, $root);
    }

    if (!str_ends_with($comment->toLatex(), "\n")) {
        throw new ParseException("Comment did not consume the newline.", 1, $root);
    }

    $serialized = $root->toLatex();
    if ($serialized !== $source) {
        throw new \Exception("Round-trip failed for comment newline.");
    }
});

$t->test("Structural: Optional argument without mandatory argument", function () use ($parser) {
    // In LaTeX, \section[Opt]{} is common, but \mycmd[Opt] is also valid.
    $source = "\\mycmd[Inside Opt] Following Text";
    $root = $parser->parse($source);

    $children = $root->getChildren();
    $cmd = $children[0];

    if (!($cmd instanceof CommandNode)) {
        throw new ParseException("Expected CommandNode, got " . get_class($cmd), 1, $root);
    }

    $cmdChildren = $cmd->getChildren();
    if (count($cmdChildren) !== 1) {
        throw new ParseException("Command should have exactly 1 child (the optional argument), found " . count($cmdChildren), 1, $root);
    }

    $optArg = $cmdChildren[0];
    if (!($optArg instanceof ArgumentNode) || !$optArg->isOptional) {
        throw new ParseException("Child should be an optional ArgumentNode", 1, $root);
    }

    $argContent = $optArg->toLatex();
    if ($argContent !== "[Inside Opt]") {
        throw new ParseException("Argument content mismatch: " . $argContent, 1, $root);
    }

    $followingText = $children[1];
    if (!($followingText instanceof TextNode) || !str_contains($followingText->content, "Following Text")) {
        throw new ParseException("Text following the command was lost or misidentified.", 1, $root);
    }
});

$t->test("Structural: Multiple optional arguments", function () use ($parser) {
    // xparse and some packages allow multiple [][][]
    $source = "\\mycmd[Opt1][Opt2]{Mandatory}";
    $root = $parser->parse($source);

    $cmd = $root->getChild(0);
    $cmdChildren = $cmd->getChildren();

    if (count($cmdChildren) !== 3) {
        throw new ParseException("Expected 3 arguments, found " . count($cmdChildren), 1, $root);
    }

    if (!$cmdChildren[0]->isOptional || !$cmdChildren[1]->isOptional || $cmdChildren[2]->isOptional) {
        throw new ParseException("Argument sequence [Opt][Opt]{Mand} not preserved correctly.", 1, $root);
    }
});

$t->test("Structural: Stray brackets treated as text", function () use ($parser) {
    $source = "The interval is [0, 1].";
    $root = $parser->parse($source);

    // There should be no ArgumentNodes here, just TextNodes.
    foreach ($root->getChildren() as $node) {
        if ($node instanceof ArgumentNode) {
            throw new ParseException("Stray '[' was incorrectly treated as an ArgumentNode", 1, $root);
        }
    }

    if ($root->toLatex() !== $source) {
        throw new ParseException("Stray bracket round-trip failed", 1, $root);
    }
});

$t->test("Structural: Mismatched brackets in text", function () use ($parser) {
    // This is valid LaTeX text: an opening bracket never closed, or vice versa.
    $source = "Check this [ and this ] separately.";
    $root = $parser->parse($source);

    if ($root->toLatex() !== $source) {
        print_doc_diff($source, $root->toLatex());
        throw new ParseException("Mismatched stray brackets failed round-trip", 1, $root);
    }
});

/**
 * --- Parser Exception Validation Suite ---
 */

$t->test("Error: Unclosed Group {", function () use ($parser) {
    try {
        $parser->parse("This is { unclosed");
        throw new \Exception("Failed to throw for unclosed group");
    } catch (ParseException $e) {
        if (!str_contains($e->getMessage(), "Unclosed delimiter '{'")) {
            throw new \Exception("Wrong error message: " . $e->getMessage());
        }
    }
});

$t->test("Error: Unclosed Environment \\begin{itemize}", function () use ($parser) {
    try {
        $parser->parse("\\begin{itemize} item without end");
        throw new \Exception("Failed to throw for unclosed environment");
    } catch (ParseException $e) {
        if (!str_contains($e->getMessage(), "Unclosed environment 'itemize'")) {
            throw new \Exception("Wrong error message: " . $e->getMessage());
        }
    }
});

$t->test("Error: Unmatched Closing Brace }", function () use ($parser) {
    try {
        $parser->parse("Text } more text");
        throw new \Exception("Failed to throw for unmatched brace");
    } catch (ParseException $e) {
        if (!str_contains($e->getMessage(), "Unmatched closing delimiter '}'")) {
            throw new \Exception("Wrong error message: " . $e->getMessage());
        }
    }
});

$t->test("Error: Mismatched Delimiters {]", function () use ($parser) {
    try {
        $parser->parse("\\section{Wrong Delimiter]");
        throw new \Exception("Failed to throw for mismatched delimiters");
    } catch (ParseException $e) {
        // Since ] is treated as text if it doesn't match, this might
        // actually throw "Unclosed delimiter '{'" at the end.
        if (!str_contains($e->getMessage(), "Unclosed delimiter '{'")) {
            throw new \Exception("Expected unclosed '{' because ']' was ignored as text. Got: " . $e->getMessage());
        }
    }
});

$t->test("Error: Invalid Hex in Catcode", function () use ($parser) {
    try {
        $parser->parse("\\catcode \"G1 = 11"); // G is not hex
        throw new \Exception("Failed to throw for invalid hex");
    } catch (ParseException $e) {
        if (!str_contains($e->getMessage(), "Invalid hex character")) {
            throw new \Exception("Wrong error message: " . $e->getMessage());
        }
    }
});

$t->test("Error: Missing Environment Name in \\end", function () use ($parser) {
    try {
        $parser->parse("\\begin{center} Content \\end");
        throw new \Exception("Failed to throw for empty \\end");
    } catch (ParseException $e) {
        // This will likely trigger a match failure in reduceStack first
        if (!str_contains($e->getMessage(), "Unclosed environment")) {
            throw new \Exception("Unexpected message: " . $e->getMessage());
        }
    }
});

$t->test("Error: Math Closer without Opener", function () use ($parser) {
    try {
        $parser->parse("Text \\) more text");
        throw new \Exception("Failed to throw for stray math closer");
    } catch (ParseException $e) {
        if (!str_contains($e->getMessage(), "Unmatched closing delimiter")) {
            throw new \Exception("Wrong error message: " . $e->getMessage());
        }
    }
});

$t->test("Error: Interleaved nesting \\begin{center} { \\end{center} }", function () use ($parser) {
    try {
        // This is invalid because the \end{center} tries to close an environment
        // that started outside the current curly brace group.
        $parser->parse("\\begin{center} { \\end{center} }");
        throw new \Exception("Failed to throw for interleaved environment/group");
    } catch (ParseException $e) {
        // Our reduceStack will fail to find the \begin{center} because
        // it only looks in the current level (the stack was spliced at the {).
        if (!str_contains($e->getMessage(), "No matching opener found")) {
            throw new \Exception("Wrong error message: " . $e->getMessage());
        }
    }
});

$t->test("Error: Environment name mismatch", function () use ($parser) {
    try {
        $parser->parse("\\begin{quote} ... \\end{itemize}");
        throw new \Exception("Failed to throw for mismatched environment names");
    } catch (ParseException $e) {
        // reduceStack will look for \begin{itemize} and not find it.
        if (!str_contains($e->getMessage(), "No matching opener found")) {
            throw new \Exception("Wrong error message: " . $e->getMessage());
        }
    }
});

$t->test("Error: Verbatim environment unclosed", function () use ($parser) {
    try {
        $parser->parse("\\begin{verbatim} raw text without end");
        throw new \Exception("Failed to throw for unclosed verbatim");
    } catch (ParseException $e) {
        // handleVerbatimEnvironment uses consumeRawUntil which should throw
        // if the closing marker is never found.
        if (!str_contains($e->getMessage(), "Unexpected end of input")) {
            throw new \Exception("Wrong error message: " . $e->getMessage());
        }
    }
});

$t->summary();
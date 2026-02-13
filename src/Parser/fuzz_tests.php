<?php

namespace Dagstuhl\Latex\Parser;

// Manual loading of the required files
require_once __DIR__ . '/Char.php';
require_once __DIR__ . '/ParseTreeNode.php';
require_once __DIR__ . '/ParseException.php';
require_once __DIR__ . '/CatcodeState.php';
require_once __DIR__ . '/LatexParser.php';
require_once __DIR__ . '/TokenType.php';
require_once __DIR__ . '/Token.php';
require_once __DIR__ . '/ParseTree.php';
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

use Dagstuhl\Latex\Parser\TreeNodes\EnvelopeNode;
use Dagstuhl\Latex\Parser\TreeNodes\RootNode;
use Dagstuhl\Latex\Parser\TreeNodes\TextNode;
use Dagstuhl\Latex\Parser\TreeNodes\WhitespaceNode;
use Dagstuhl\Latex\Parser\TreeNodes\MathNode;
use Dagstuhl\Latex\Parser\TreeNodes\EnvironmentNode;
use Dagstuhl\Latex\Parser\TreeNodes\MathEnvironmentNode;
use Dagstuhl\Latex\Parser\TreeNodes\CommandNode;
use Dagstuhl\Latex\Parser\TreeNodes\ArgumentNode;
use Dagstuhl\Latex\Parser\TreeNodes\GroupNode;
use Dagstuhl\Latex\Parser\TreeNodes\CommentNode;
use Dagstuhl\Latex\Parser\TreeNodes\VerbNode;

class FuzzTester
{
    private array $textEnvs = [ 'center', 'description', 'quote' ];
    private array $mathEnvs = [ 'displaymath', 'align', 'equation' ];
    private int $lineNumber;

    public function run(int $iterations): void
    {
        $parser = new LatexParser();
        $isSingleSeed = $iterations < 0;
        $seed = $isSingleSeed ? abs($iterations) : mt_rand();
        $count = $isSingleSeed ? 1 : $iterations;

        echo "Fuzz Run Started (Seed: $seed)\n";
        echo str_pad("Iteration", 12) . str_pad("TreeNodes", 10) . str_pad("Depth", 8) . "Result\n";
        echo str_repeat("-", 45) . "\n";

        for ($i = 1; $i <= $count; $i++)
        {
            mt_srand($seed);

            $this->lineNumber = 1;
            $originalTree = $this->createRootNode(3);

            $stats = $this->getTreeStats($originalTree);
            $latex = $originalTree->toLatex();
            $parsedTree = null;

            try
            {
                $parsedTree = $parser->parse($latex);
                $this->compareTrees($originalTree, $parsedTree->root);
                printf("%-12d %-10d %-8d PASSED\n", $i, $stats['nodes'], $stats['depth']);
            }
            catch (\Exception $e)
            {
                echo "\n[!] Fuzzing Failure at Iteration $i\n";
                echo "Reproduce: php fuzz_tests.php -$seed\n";
                echo "Error: " . $e->getMessage() . "\n";
                echo "--- Tree Comparison (Original vs Parsed) ---\n";
                if ($parsedTree === null) {
                    echo "Parsing failed. Original tree was:\n";
                    echo $originalTree->toTreeString() . "\n";
                } else {
                    echo $this->getTreeComparisonString($originalTree, $parsedTree->root);
                }
                echo "\n--- Generated LaTeX ---\n$latex\n-----------------------\n";
                exit(1);
            }

            $seed = mt_rand();
        }
    }

    private function getTreeStringWithLines(ParseTreeNode $node): string
    {
        $ret = "$node";

        if ($node->getChildCount() > 0) {
            for ($i = 0, $n = $node->getChildCount(); $i < $n; $i++) {
                $childLines = explode("\n", $this->getTreeStringWithLines($node->getChild($i)));

                if ($i === $n - 1) {
                    $ret .= "\n└┄" . $node->getChild(-1);
                } else {
                    $ret .= "\n├┄" . $childLines[0];
                }

                for ($j = 1, $m = count($childLines); $j < $m; $j++) {
                    if ($i === $n - 1) {
                        $ret .= "\n  " . $childLines[$j];
                    } else {
                        $ret .= "\n┊ " . $childLines[$j];
                    }
                }
            }
        }

        return $ret;
    }

    private function getTreeComparisonString(ParseTreeNode $original, ?ParseTreeNode $parsed): string
    {
        if ($parsed === null)
        {
            return $this->getTreeStringWithLines($original);
        }

        $originalLines = explode("\n", $this->getTreeStringWithLines($original));
        $parsedLines = explode("\n", $this->getTreeStringWithLines($parsed));

        $columnWidth = 0;
        foreach ($originalLines as $originalLine)
        {
            $columnWidth = max($columnWidth, mb_strlen($originalLine));
        }

        $str = '';
        $error = false;
        $m = min(count($originalLines), count($parsedLines));
        $n = max(count($originalLines), count($parsedLines));

        for ($i = 0; $i < $n; $i++) {
            $mark = "  ";

            if (!$error && $i < $m) {
                if ($originalLines[$i] !== $parsedLines[$i]) {
                    $mark = "**";
                    $error = true;
                }
            }

            if ($i < count($originalLines)) {
                $str .= $originalLines[$i] . str_repeat(" ", $columnWidth - mb_strlen($originalLines[$i]));
            } else {
                $str .= str_repeat(" ", $columnWidth);
            }

            if ($i < count($parsedLines)) {
                $str .= " ";
                $str .= $mark . $parsedLines[$i] . $mark;
            }

            $str .= "\n";
        }

        return $str;
    }

    private function createRootNode(int $depth): RootNode
    {
        $node = new RootNode();
        $types = ['text', 'whitespace', 'command', 'environment', 'math', 'group', 'comment', 'verb'];
        $this->fillContainer($node, $depth, $types);
        return $node;
    }

    private function generateRandomNode(array $possibleTypes, int $depth): ParseTreeNode
    {
        $type = $possibleTypes[array_rand($possibleTypes)];

        $node = match ($type)
        {
            'text'        => new TextNode($this->lineNumber, $this->generateRandomText()),
            'whitespace'  => new WhitespaceNode($this->lineNumber, " "),
            'command'     => $this->createCommandNode($depth),
            'environment' => $this->createEnvironmentNode($depth),
            'math'        => $this->createMathNode($depth),
            'group'       => $this->createGroupNode($depth),
            'comment'     => new CommentNode($this->lineNumber, "% " . $this->generateRandomText() . "\n"),
            'verb'        => new VerbNode($this->lineNumber, "verb", "|"),
            default       => new TextNode($this->lineNumber, "fallback")
        };

        if (!$node instanceof EnvelopeNode && $node->getChildCount() === 1) {
            $child = $node->getChild(0);
            if ($child instanceof WhitespaceNode || $child instanceof CommentNode) {
                $node->removeChild(0);
            }
        }

        return $node;
    }

    private function generateRandomText(): string
    {
        $vowels = "aeiou";
        $consonants = "bcdfghjklmnpqrstvxz";
        $sentences = "";

        $numSentences = 4 - ceil(log(mt_rand(2, 7), 2));
        for ($i = 1; $i <= $numSentences; $i++) {
            $sentence = "";

            $numWords = 8 - ceil(log(mt_rand(2, 127), 2));
            for ($j = 0; $j < $numWords; $j++) {
                $word = "";

                while (strlen($word) === 0) {
                    $numSyllables = 4 - ceil(log(mt_rand(2, 7), 2));
                    for ($k = 0; $k < $numSyllables; $k++) {
                        $numConsonants = 4 - ceil(log(mt_rand(2, 7), 2));
                        for ($l = 0; $l < $numConsonants; $l++) {
                            $word .= $consonants[mt_rand(0, strlen($consonants) - 1)];
                        }

                        $numVowels = $k + 1 == $numSyllables && $k > 0 ? mt_rand(0, 2) / 2 : 1;
                        for ($l = 0; $l < $numVowels; $l++) {
                            $word .= $vowels[mt_rand(0, strlen($vowels) - 1)];
                        }
                    }
                }

                $sentence .= " " . $word;
            }

            if (strlen($sentence) > 2) {
                $sentences .= " " . strtoupper(substr($sentence, 1, 1));
                $sentences .= substr($sentence, 2);
                $sentences .= ".";
            }
        }

        if (strlen($sentences) > 0) {
            return substr($sentences, 1);
        } else {
            return "This is a sentence.";
        }
    }

    private function fillContainer(ParseTreeNode $container, int $depth, array $allowedTypes): void
    {
        if ($depth <= 0) {
            return;
        }

        $numChildren = mt_rand(1, 4);
        for ($n = $container->getChildCount(); $container->getChildCount() < $n + $numChildren;) {
            $child = $this->generateRandomNode($allowedTypes, $depth);

            // This handles merging, swallowing, and the Command+Group boundary rule
            $this->addChildSafely($container, $child);

            // Comments in LaTeX effectively swallow the rest of the line,
            // so we force a newline whitespace to follow it for round-trip sanity.
            if ($child instanceof CommentNode) {
                $this->lineNumber++;
            }
        }
    }

    private function addChildSafely(ParseTreeNode $parent, ParseTreeNode $child): void
    {
        $offset = $parent instanceof EnvelopeNode ? 1 : 0;

        $lastContentNode = null;
        for ($i = $parent->getChildCount() - 1 - $offset; $i >= $offset; --$i) {
            $existingChild = $parent->getChild($i);
            if (!($existingChild instanceof CommentNode || $existingChild instanceof WhitespaceNode)) {
                $lastContentNode = $existingChild;
                break;
            }
        }

        $last = $parent->getChildCount() > $offset * 2 ? $parent->getChild(-1 - $offset) : null;

        // 1. BOUNDARY RULE: Prevent GroupNode from accidentally becoming a Command Argument
        if ($child instanceof GroupNode) {
            if (
                $lastContentNode instanceof CommandNode ||
                $lastContentNode instanceof ArgumentNode
            ) {
                return;
            }

            if ($parent instanceof EnvironmentNode && $lastContentNode === null) {
                return;
            }
        }

        if ($child instanceof MathNode && $last instanceof MathNode) {
            if (str_ends_with($last->toLatex(), "$") &&
                str_starts_with($child->toLatex(), "$")) {
                return;
            }
        }

        if ($last instanceof CommandNode &&
            $last->getChildCount() === 1 &&
            $child instanceof TextNode) {
            return;
        }

        if ($last instanceof WhitespaceNode) {
            if ($child instanceof WhitespaceNode) {
                $last->content .= $child->content;
                return;
            } elseif ($child instanceof TextNode) {
                $parent->removeChild(-1 - $offset);
                $child->content = $last->content . $child->content;
                return;
            }
        } elseif ($last instanceof TextNode) {
            if ($child instanceof TextNode) {
                $last->content .= $child->content;
                return;
            }
        }

        $parent->addChild($child);
    }

    private function createArgumentNode(int $depth, bool $isOptional): ArgumentNode
    {
        $node = new ArgumentNode($this->lineNumber, $isOptional);
        // Removed 'argument' from here to prevent nesting
        $types = ['text', 'whitespace', 'command', 'group'];
        if ($depth > 0) $this->fillContainer($node, $depth - 1, $types);
        return $node;
    }

    private function createCommandNode(int $depth): CommandNode
    {
        $node = new CommandNode($this->lineNumber, "\\" . ['section', 'textbf', 'textit'][mt_rand(0, 2)]);

        if ($depth > 0) {
            $optionalArgCount = mt_rand(0, 1);

            for ($i = 0; $i < $optionalArgCount; $i++) {
                $roll = mt_rand(0, 10);
                if ($roll === 0) {
                    $node->addChild(new WhitespaceNode($this->lineNumber, " "));
                }

                $node->addChild($this->createArgumentNode($depth - 1, true));
            }

            $mandatoryArgCount = mt_rand(0, 2);
            for ($i = 0; $i < $mandatoryArgCount; $i++) {
                $roll = mt_rand(0, 10);
                if ($roll === 0) {
                    $node->addChild(new WhitespaceNode($this->lineNumber, " "));
                }

                $node->addChild($this->createArgumentNode($depth - 1, false));
            }
        }

        return $node;
    }

    private function createEnvironmentNode(int $depth): EnvironmentNode
    {
        $isMath = (bool)mt_rand(0, 1);
        $name = $isMath ? $this->mathEnvs[array_rand($this->mathEnvs)] : $this->textEnvs[array_rand($this->textEnvs)];

        $begin = new CommandNode($this->lineNumber, "\\begin");
        $begin->addChild($this->createStaticArg($name));
        $end = new CommandNode($this->lineNumber, "\\end");
        $end->addChild($this->createStaticArg($name));

        $node = $isMath
            ? new MathEnvironmentNode($name, $begin, $end)
            : new EnvironmentNode($name, $begin, $end);

        $types = $isMath ? ['text', 'whitespace', 'command'] : ['text', 'whitespace', 'command', 'group', 'environment'];
        if ($depth > 0) $this->fillContainer($node, $depth - 1, $types);
        return $node;
    }

    private function createMathNode(int $depth): MathNode
    {
        $delimiterPairs = [['$$', '$$'], ['\\[', '\\]'], ['\\(', '\\)']];
        if ($depth > 0) {
            $delimiterPairs[] = ['$', '$'];
        }

        $delimiters = $delimiterPairs[array_rand($delimiterPairs)];

        $lineNumber = $this->lineNumber;

        $opener = new CommandNode($lineNumber, $delimiters[0]);
        $closer = new CommandNode($lineNumber, $delimiters[1]);

        $node = new MathNode($this->lineNumber, $opener, $closer);

        if ($depth > 0) $this->fillContainer($node, $depth - 1, ['text', 'whitespace', 'command']);

        return $node;
    }

    private function createGroupNode(int $depth): GroupNode
    {
        $node = new GroupNode($this->lineNumber);
        if ($depth > 0) $this->fillContainer($node, $depth - 1, ['text', 'whitespace', 'command', 'group']);
        return $node;
    }

    private function createStaticArg(string $text): ArgumentNode
    {
        $arg = new ArgumentNode($this->lineNumber, false);
        $arg->addChild(new TextNode($this->lineNumber, $text));
        return $arg;
    }

    private function getTreeStats(ParseTreeNode $node): array
    {
        $total = 1;
        $maxD = 0;
        foreach ($this->getRawChildren($node) as $c)
        {
            $s = $this->getTreeStats($c);
            $total += $s['nodes'];
            $maxD = max($maxD, $s['depth']);
        }
        return ['nodes' => $total, 'depth' => 1 + $maxD];
    }

    private function compareTrees(ParseTreeNode $o, ParseTreeNode $p): void
    {
        if (get_class($o) !== get_class($p) || $o->toLatex() !== $p->toLatex())
        {
            throw new \Exception("Mismatch in " . get_class($o));
        }
        $oc = $this->getRawChildren($o);
        $pc = $this->getRawChildren($p);
        if (count($oc) !== count($pc))
        {
            throw new \Exception("Child count mismatch");
        }
        foreach ($oc as $i => $child)
        {
            $this->compareTrees($child, $pc[$i]);
        }
    }

    private function getRawChildren(ParseTreeNode $node): array
    {
        $r = new \ReflectionObject($node);
        while (!$r->hasProperty('children')) $r = $r->getParentClass();
        $prop = $r->getProperty('children');
        $prop->setAccessible(true);
        return $prop->getValue($node);
    }
}

// CLI Execution
$count = isset($argv[1]) ? (int)$argv[1] : 1;
(new FuzzTester())->run($count);


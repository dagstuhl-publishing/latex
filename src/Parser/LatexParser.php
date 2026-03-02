<?php

namespace Dagstuhl\Latex\Parser;

use Dagstuhl\Latex\Parser\TreeNodes\ArgumentNode;
use Dagstuhl\Latex\Parser\TreeNodes\CommandNode;
use Dagstuhl\Latex\Parser\TreeNodes\CommentNode;
use Dagstuhl\Latex\Parser\TreeNodes\EnvelopeNode;
use Dagstuhl\Latex\Parser\TreeNodes\EnvironmentNode;
use Dagstuhl\Latex\Parser\TreeNodes\GroupNode;
use Dagstuhl\Latex\Parser\TreeNodes\MathEnvironmentNode;
use Dagstuhl\Latex\Parser\TreeNodes\MathNode;
use Dagstuhl\Latex\Parser\TreeNodes\RootNode;
use Dagstuhl\Latex\Parser\TreeNodes\TextNode;
use Dagstuhl\Latex\Parser\TreeNodes\UnclosedGroupNode;
use Dagstuhl\Latex\Parser\TreeNodes\VerbNode;
use Dagstuhl\Latex\Parser\TreeNodes\WhitespaceNode;
use PhpParser\Node\Arg;

class LatexParser
{
    private const MATH_ENVIRONMENTS = [
        'math',
        'displaymath',
        'equation', 'equation*',
        'eqnarray', 'eqnarray*',
        'align', 'align*',
        'alignat', 'alignat*',
        'flalign', 'flalign*',
        'gather', 'gather*',
        'multline', 'multline*',
        'cases*',
        'dcases', 'dcases*',
        'rcases', 'rcases*'
    ];

    private const RAW_ENVIRONMENTS = [
        'alltt', 'abscode', 'allinlustre', 'allinlustre-figure', 'AnerisPLsmall', 'ainflisting',
        'bull', 'BVerbatim',
        'chorlisting', 'chorallisting', 'code', 'codeblock', 'codeblockcss', 'codejava', 'coq', 'coqlisting', 'clang-figure', 'clang',
        'excerpt', 'excerpt*', 'easycrypt',
        'FigureVerbatim',
        'granule', 'gql*', 'haskell', 'Highlighting',
        'InlineVerbatim', 'isabelle',
        'javacode', 'javan', 'javalisting',
        'listingLemma', 'listingJolie', 'lstlisting', 'longcode', 'langlisting', 'leanlisting',
        'minted', 'mcode', 'mzn', 'myequations',
        'numcodejava', 'nicehaskell', 'numpylisting', 'numberedprogram',
        'ocalm', 'OCAMLLISTING',
        'pecan', 'program', 'PYTHONLISTING', 'PYTHONLISTINGGNOLINENO', 'pseudolisting',
        'rustlisting',
        'scalalisting',
        'verbatim', 'VerbatimFigure'
    ];

    private const MACRO_ARGUMENT_COUNTS = [];
    private const MACROS_BY_ARGUMENT_COUNT = [
        // macros that do not take an argument
        ['cup'],
        // macros with a single argument
        ['section']
    ];

    const CAT_CODE_ESCAPE = 0;
    const CAT_CODE_GROUP_BEGIN = 1;
    const CAT_CODE_GROUP_END = 2;
    const CAT_CODE_MATH_SHIFT = 3;
    const CAT_CODE_ALIGNMENT_TAB = 4;
    const CAT_CODE_END_OF_LINE = 5;
    const CAT_CODE_PARAMETER = 6;
    const CAT_CODE_SUPERSCRIPT = 7;
    const CAT_CODE_SUBSCRIPT = 8;
    const CAT_CODE_IGNORE = 9;
    const CAT_CODE_SPACE = 10;
    const CAT_CODE_LETTER = 11;
    const CAT_CODE_OTHER = 12;
    const CAT_CODE_ACTIVE_CHAR = 13;
    const CAT_CODE_COMMENT_CHAR = 14;
    const CAT_CODE_INVALID_CHAR = 15;

    public function __construct()
    {
        for ($i = 0; $i < count(self::MACROS_BY_ARGUMENT_COUNT); $i++) {
            foreach (self::MACROS_BY_ARGUMENT_COUNT[$i] as $macroName) {
                self::MACRO_ARGUMENT_COUNTS[$macroName] = $i;
            }
        }
    }

    /**
     * @throws ParseException
     */
    public function parse(string $source): ParseTree
    {
        $source = str_replace("\r", "\n", str_replace("\r\n", "\n", $source));

        $catCodes = array_fill(0, 256, self::CAT_CODE_OTHER);
        for ($i = 65, $j = 97; $i <= 90; $i++, $j++) {
            $catCodes[$i] = self::CAT_CODE_LETTER;
            $catCodes[$j] = self::CAT_CODE_LETTER;
        }
        $catCodes[0] = self::CAT_CODE_IGNORE;
        $catCodes[9] = self::CAT_CODE_SPACE;
        $catCodes[10] = self::CAT_CODE_END_OF_LINE;
        $catCodes[32] = self::CAT_CODE_SPACE;
        //$catCodes[35] = self::CAT_CODE_PARAMETER;
        $catCodes[36] = self::CAT_CODE_MATH_SHIFT;
        $catCodes[37] = self::CAT_CODE_COMMENT_CHAR;
        //$catCodes[38] = self::CAT_CODE_ALIGNMENT_TAB;
        $catCodes[92] = self::CAT_CODE_ESCAPE;
        //$catCodes[94] = self::CAT_CODE_SUPERSCRIPT;
        //$catCodes[95] = self::CAT_CODE_SUBSCRIPT;
        $catCodes[123] = self::CAT_CODE_GROUP_BEGIN;
        $catCodes[125] = self::CAT_CODE_GROUP_END;
        $catCodes[126] = self::CAT_CODE_ACTIVE_CHAR;
        $catCodes[127] = self::CAT_CODE_INVALID_CHAR;

        // nodes that might receive children in the future
        // are stored on a stack when they're no longer
        // the currentNode.
        $rootNode = new RootNode();
        $stack = [$rootNode];

        $lineNumber = 1;

        $acceptingArguments = false;
        $buffer = [];

        $dollarIndices = [-1];
        $doubleDollarIndices = [-1];
        $curlyIndices = [-1];

        $prevNode = null;

//        echo "\n";

        $i = 0;
        for ($n = strlen($source); $i < $n; $i++) {
            $char = $source[$i];
            $catCode = $catCodes[ord($char)];
            $node = null;

//            echo "⎡stack (" . count($stack) . ") [" . implode(', ', $stack) . "]\n";
//            echo "⎢char: \e[47m\e[30m" . str_replace("\n", '\n', $char) . "\e[0m catCode: $catCode\n";
//            echo "⎣line number: $lineNumber, accepting arguments: " . ($acceptingArguments ? "true" : "false" ) . "\n";

            switch ($catCode) {
                case self::CAT_CODE_ESCAPE:
                    if ($i + 1 < $n && $catCodes[ord($source[$i + 1])] !== self::CAT_CODE_LETTER) {
                        $commandName = substr($source, $i, 2);

                        if ($commandName === '\(' || $commandName === '\[') {
                            $closingName = $commandName === '\(' ? '\)' : '\]';
                            $node = new MathNode($lineNumber, new CommandNode($lineNumber, $commandName), new CommandNode($lineNumber, $closingName));
                        } else if ($commandName === '\)' || $commandName === '\]') {
                            $k = count($stack) - 1;

                            for (; $k >= 0; $k--) {
                                if ($stack[$k] instanceof MathNode &&
                                    $stack[$k]->getClosing()->getText() === $commandName) {
                                    break;
                                }
                            }

                            if ($k >= 0) {
                                $this->reduceNode($stack, $k, $dollarIndices, $doubleDollarIndices, $buffer, "called from MathNode closer $commandName");
                                $prevNode = array_pop($stack);
                                /** @var EnvelopeNode $prevNode */
                                $prevNode->getClosing()->lineNumber = $lineNumber;
                            } else {
                                $node = new CommandNode($lineNumber, $commandName);
                            }
                        } else {
                            $node = new CommandNode($lineNumber, $commandName);
                        }
                        $i++;
                    } else {
                        $j = $i + 1;
                        for (; $j < $n; $j++) {
                            if ($catCodes[ord($source[$j])] !== self::CAT_CODE_LETTER) {
                                break;
                            }
                        }
                        $commandName = substr($source, $i, $j - $i);

                        if ($commandName === '\\verb') {
                            $i = $j;
                            if ($j < $n) {
                                $delimiter = $source[$j];
                                for ($j++; $j < $n; $j++) {
                                    if ($source[$j] === $delimiter) {
                                        break;
                                    }
                                }

                                if ($j < $n) {
                                    $content = substr($source, $i + 1, $j - $i - 1);
                                    $lineNumber += substr_count($content, "\n");
                                    $node = new VerbNode($lineNumber, $content, $delimiter);
                                } else {
                                    throw new ParseException("Unclosed \verb command.", $lineNumber);
                                }
                            } else {
                                $node = new CommandNode($lineNumber, $commandName);
                            }
                            $i = $j;
                        } else {
                            $node = new CommandNode($lineNumber, $commandName);
                            $i = $j - 1;
                        }
                    }
                    break;

                case self::CAT_CODE_GROUP_BEGIN:
//                    echo "pushing curlyIndex: $i\n";
                    $curlyIndices[] = $i;
                    if ($acceptingArguments) {
                        $node = new ArgumentNode($lineNumber, false);
                    } else {
                        $node = new GroupNode($lineNumber);
                    }
                    break;

                case self::CAT_CODE_GROUP_END:
                    $curlyOpener = array_pop($curlyIndices);
//                    echo "popping curlyIndex: $curlyOpener @ $i\n";

                    $k = count($stack) - 1;
                    for (; $k >= 0; --$k) {
                        $stackNode = $stack[$k];
                        if (($stackNode instanceof ArgumentNode && !$stackNode->isOptional) ||
                            $stackNode instanceof GroupNode) {
                            break;
                        }
                    }
                    if ($k >= 0) {
                        $this->reduceNode($stack, $k, $dollarIndices, $doubleDollarIndices, $buffer, "called from " . get_class($stackNode) . " closer");
                        $prevNode = array_pop($stack);

                        $commandNode = $prevNode->parent;
                        if ($prevNode instanceof ArgumentNode &&
                            !$prevNode->isOptional &&
                            $commandNode->getArgumentCount() === 1) {

                            if ($commandNode->getName() === '\\begin') {
                                array_pop($stack);
                                $parent = $commandNode->parent;
                                $envName = $prevNode->getText();

                                if (in_array($envName, self::RAW_ENVIRONMENTS)) {
                                    $contentLineNumber = $lineNumber;
                                    $closingString = "\\end{{$envName}}";

                                    $i++;
                                    $j = strpos($source, $closingString, $i);
                                    if ($j === false) {
                                        throw new ParseException("Unmatched \\begin{{$envName}}", $lineNumber);
                                    }

                                    $content = substr($source, $i, $j - $i);
                                    $lineNumber += substr_count($content, "\n");

                                    $closing = new CommandNode($lineNumber, '\\end');
                                    $closingArg = new ArgumentNode($lineNumber, false);
                                    $closingArgText = new TextNode($lineNumber, $envName);
                                    $closingArg->addChild($closingArgText);
                                    $closing->addChild($closingArg);

                                    $envNode = new EnvironmentNode($envName, $commandNode, $closing);
                                    $envNode->addChild(new TextNode($contentLineNumber, $content));
                                    $parent->addChild($envNode);
                                    $prevNode = $envNode;
                                    $i = $j + strlen($closingString) - 1;
                                } else {
                                    $closing = new CommandNode(-1, '\\end');
                                    $closingArg = new ArgumentNode(-1, false);
                                    $closingArgText = new TextNode(-1, $envName);
                                    $closingArg->addChild($closingArgText);
                                    $closing->addChild($closingArg);

                                    if (in_array($envName, self::MATH_ENVIRONMENTS)) {
                                        $envNode = new MathEnvironmentNode($envName, $commandNode, $closing);
                                    } else {
                                        $envNode = new EnvironmentNode($envName, $commandNode, $closing);
                                    }

                                    $parent->addChild($envNode);
                                    $stack[] = $envNode;
                                    $stack[] = $commandNode;
                                }
                            } elseif ($commandNode->getName() === '\\end') {
                                array_pop($stack);
                                $envName = $prevNode->getText();

                                $k = count($stack) - 1;
                                for (; $k >= 0; $k--) {
                                    $stackNode = $stack[$k];
                                    if ($stackNode instanceof EnvironmentNode &&
                                        $stackNode->getName() === $envName) {
                                        break;
                                    }
                                }

                                if ($k >= 0) {
                                    $commandNode->parent->removeChild($commandNode);
                                    $this->reduceNode($stack, $k, $dollarIndices, $doubleDollarIndices, $buffer,"called from \\end\{$envName}\}");
                                    $envNode = array_pop($stack);

                                    // when creating the parent, we used a placeholder $closing node
                                    // which we now have to update to correct for its line number and
                                    // possibly whitespace or comment nodes
                                    /** @var EnvelopeNode $envNode */
                                    $closing = $envNode->getClosing();
                                    $closing->lineNumber = $commandNode->lineNumber;
                                    $closing->getChild(0)->lineNumber = $prevNode->lineNumber;

                                    while ($commandNode->getChildCount() > 2) {
                                        $child = $commandNode->removeChild(-2);
                                        $closing->addChild($child, 1);
                                    }

                                    $prevNode = $commandNode;
                                } else {
                                    // no matching \begin{$envName}
                                }
                            }
                        }
                    } else {
                        throw new ParseException("Unmatched }", $lineNumber);
                    }
                    break;

                case self::CAT_CODE_MATH_SHIFT:
                    if (
                        $i + 1 < $n &&
                        $catCodes[ord($source[$i + 1])] === self::CAT_CODE_MATH_SHIFT &&
                        end($dollarIndices) <= end($doubleDollarIndices)
                    ) {
                        $i++;
                        $char .= $source[$i];
                        $mathIndices = &$doubleDollarIndices;
                    } else {
                        $mathIndices = &$dollarIndices;
                    }

                    if (end($curlyIndices) < end($mathIndices)) {
                        $mathIndex = array_pop($mathIndices);
//                        echo "popping mathIndex: $mathIndex\n";
                        $k = count($stack) - 1;

                        for (; $k >= 0; $k--) {
                            if ($stack[$k] instanceof MathNode &&
                                $stack[$k]->getClosing()->getText() === $char) {
                                break;
                            }
                        }

                        $this->reduceNode($stack, $k, $dollarIndices, $doubleDollarIndices, $buffer, "called from end of MathNode ($char)");
                        $prevNode = array_pop($stack);
                        /** @var EnvelopeNode $prevNode */
                        $prevNode->getClosing()->lineNumber = $lineNumber;
                    } else {
                        $mathIndices[] = $i;
//                        echo "pushing mathIndex: $i\n";
                        $node = new MathNode($lineNumber, new CommandNode($lineNumber, $char), new CommandNode($lineNumber, $char));
                    }
                    break;

                //case self::CAT_CODE_ALIGNMENT_TAB:
                //    break;
                //case self::CAT_CODE_PARAMETER:
                //    break;
                //case self::CAT_CODE_SUPERSCRIPT:
                //    break;
                //case self::CAT_CODE_SUBSCRIPT:
                //    break;
                case self::CAT_CODE_IGNORE:
                    // ignore
                    break;

                case self::CAT_CODE_OTHER:
                    $stackIndex = count($stack) - 1;
                    $nonCommandEndOfStack = end($stack);
                    if ($nonCommandEndOfStack instanceof CommandNode) {
                        $stackIndex--;
                        $nonCommandEndOfStack = $stack[$stackIndex];
                    }

                    if ($char === '[' && $acceptingArguments) {
                        $node = new ArgumentNode($lineNumber, true);
                        break;
                    } else if (
                        $char === ']' &&
                        $nonCommandEndOfStack instanceof ArgumentNode &&
                        $nonCommandEndOfStack->isOptional
                    ) {
                        $this->reduceNode($stack, $stackIndex, $dollarIndices, $doubleDollarIndices, $buffer);
                        $prevNode = array_pop($stack);
                        break;
                    } else if (
                        $char === ',' &&
                        $nonCommandEndOfStack instanceof ArgumentNode &&
                        $nonCommandEndOfStack->isOptional
                    ) {
                        $node = new TextNode($lineNumber, $char);
                        break;
                    }

                    // NOTE: intended fall-through in 'else' case

                case self::CAT_CODE_END_OF_LINE:
                case self::CAT_CODE_SPACE:
                case self::CAT_CODE_LETTER:
                    $isWhitespace = ($catCode === self::CAT_CODE_SPACE || $catCode === self::CAT_CODE_END_OF_LINE);
                    $nodeLineNumber = $lineNumber;
                    if ($catCode === self::CAT_CODE_END_OF_LINE) {
                        $lineNumber++;
                    }

                    $j = $i + 1;
                    for (; $j < $n; $j++) {
                        $catCode = $catCodes[ord($source[$j])];
                        if ($catCode === self::CAT_CODE_LETTER) {
                            $isWhitespace = false;
                        } else if ($catCode === self::CAT_CODE_END_OF_LINE) {
                            $lineNumber++;
                        } else if ($catCode !== self::CAT_CODE_SPACE) {
                            break;
                        }
                    }

                    if ($isWhitespace) {
                        if ($prevNode instanceof TextNode) {
                            $prevNode->content .= substr($source, $i, $j - $i);
                        } else {
                            $node = new WhitespaceNode($nodeLineNumber, substr($source, $i, $j - $i));
                        }
                    } else {
                        if (
                            $prevNode instanceof TextNode &&
                            !$prevNode instanceof WhitespaceNode &&
                            (
                                $prevNode->getText() !== ',' ||
                                !($prevNode->parent instanceof ArgumentNode) ||
                                !$prevNode->parent->isOptional
                            )
                        ) {
                            $prevNode->content .= substr($source, $i, $j - $i);
                        } else {
                            $node = new TextNode($nodeLineNumber, substr($source, $i, $j - $i));
                        }
                    }

                    $i = $j - 1;
                    break;

                case self::CAT_CODE_ACTIVE_CHAR:
                    $node = new CommandNode($lineNumber, $char);
                    break;

                case self::CAT_CODE_COMMENT_CHAR:
                    $j = $i + 1;
                    for (; $j < $n; $j++) {
                        $catCode = $catCodes[ord($source[$j])];
                        if ($catCode === self::CAT_CODE_END_OF_LINE) {
                            $j++;
                            break;
                        }
                    }

                    $node = new CommentNode($lineNumber, substr($source, $i, $j - $i));
                    $lineNumber++;
                    $i = $j - 1;
                    break;

                case self::CAT_CODE_INVALID_CHAR:
                    throw new ParseException("Document contains invalid char `$char'.", $lineNumber);
            }

//            if ($node !== null) {
//                echo "Creating new $node (current stack top: " . (end($stack)) . ")\n";
//            }

            if ($acceptingArguments) {
                if ($node instanceof CommentNode || $node instanceof WhitespaceNode) {
                    $buffer[] = $node;
                } else if ($node instanceof ArgumentNode) {
                    $commandNode = end($stack);
                    if (!empty($buffer)) {
                        $commandNode->addChildren($buffer);
                        $buffer = [];
                    }
                    $commandNode->addChild($node);
                    $stack[] = $node;
                } else if ($node !== null) {
                    array_pop($stack);
                    if (!empty($buffer)) {
                        end($stack)->addChildren($buffer);
                        $buffer = [];
                    }
                    end($stack)->addChild($node);

                    if ($node instanceof EnvelopeNode || $node instanceof CommandNode) { // TODO: probably not *all* EnvelopeNodes
                        $stack[] = $node;
                    }
                }
            } else if ($node !== null) {
                end($stack)->addChild($node);

                if ($node instanceof EnvelopeNode || $node instanceof CommandNode) { // TODO: probably not *all* Envelopenodes
                    $stack[] = $node;
                }
            }

            $top = end($stack);
            $acceptingArguments = (
                ($top instanceof CommandNode) &&
                (
                    !isset(self::MACRO_ARGUMENT_COUNTS[$top->getName()]) ||
                    $top->getArgumentCount(false) < self::MACRO_ARGUMENT_COUNTS[$top->getName()]
                )
            );


            if ($node !== null) {
                $prevNode = $node;
            }
        }

        if (!empty($buffer)) {
            $rootNode->addChildren($buffer);
        }

        return new ParseTree($rootNode);
    }

    private function reduceNode(array &$stack, int $stackIndex, &$dollarIndices, &$doubleDollarIndices, &$buffer, string $debugInfo = ''): void
    {
//        echo "---<reduceNode>------------\n";
//        echo "reduceNode" . ($debugInfo !== '' ? ", $debugInfo" : '') . "\n";
//        echo "until node ($stackIndex): ";
//        echo "$stack[$stackIndex]\n";
//        echo "stack: [" . implode(', ', $stack) . "]\n";

        $node = $stack[$stackIndex];
        $stackSize = count($stack);
        $children = [];

        for ($i = $stackIndex + 1; $i < $stackSize; $i++) {
            $reducedNode = $stack[$i];
            if (!($reducedNode instanceof CommandNode ||
                  $reducedNode->parent === null)) {
                $reducedNode->parent->removeChild($reducedNode);
            }
        }

        for ($i = $stackIndex + 1; $i < $stackSize; $i++) {
            $reducedNode = $stack[$i];
            if ($reducedNode instanceof CommandNode) {
                if ($i < $stackSize - 1 &&
                    $reducedNode->getChildCount() > 1 &&
                    $stack[$i + 1] === $reducedNode->getChild(-1)) {
                    $reducedNode->removeChild(-1);

                    if ($reducedNode->getChild(-1) instanceof WhitespaceNode) {
                        $children[] = $reducedNode->removeChild(-1);
                    }
                }
            } else {
                if ($reducedNode->getChildCount() > 0) {
                    array_push($children, ...$reducedNode->getChildren());
                    array_pop($children);
                }

                if ($reducedNode instanceof MathNode) {
                    if ($reducedNode->getOpening()->getText() === '$') {
                        array_pop($dollarIndices);
                    } elseif ($reducedNode->getOpening()->getText() === '$$') {
                        array_pop($doubleDollarIndices);
                    }
                }
            }
        }

        for ($i = $stackIndex + 1; $i < $stackSize; $i++) {
            array_pop($stack);
        }

        if (!empty($children)) {
            $node->addChildren($children);
        }

        if (!empty($buffer)) {
            $node->addChildren($buffer);
            $buffer = [];
        }

//        echo "---</reducenode>------------\n";
    }
}
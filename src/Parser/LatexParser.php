<?php

namespace Dagstuhl\Latex\Parser;

use Dagstuhl\Latex\Parser\TreeNodes\ArgumentNode;
use Dagstuhl\Latex\Parser\TreeNodes\CommandNode;
use Dagstuhl\Latex\Parser\TreeNodes\CommentNode;
use Dagstuhl\Latex\Parser\TreeNodes\EnvironmentNode;
use Dagstuhl\Latex\Parser\TreeNodes\GroupNode;
use Dagstuhl\Latex\Parser\TreeNodes\MathEnvironmentNode;
use Dagstuhl\Latex\Parser\TreeNodes\MathNode;
use Dagstuhl\Latex\Parser\TreeNodes\RootNode;
use Dagstuhl\Latex\Parser\TreeNodes\TextNode;
use Dagstuhl\Latex\Parser\TreeNodes\UnclosedGroupNode;
use Dagstuhl\Latex\Parser\TreeNodes\VerbNode;
use Dagstuhl\Latex\Parser\TreeNodes\WhitespaceNode;

class LatexParser
{
    private array $mathEnvironments = [
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

    private array $rawEnvironments = [
        'verbatim',
        'lstlisting',
        'minted',
        'comment'
    ];

    /**
     * @throws ParseException
     */
    public function parse(string $source): ParseTree
    {
        $lexer = new Lexer($source);

        $stack = [];
        $curlyStack = []; // stores indices into $stack to all occurrences of {
        $delimiterStack = []; // stores [$delimiter, $stackIndex] for all opening delimiters
        $beginStack = []; // stores [$envName, $stackIndex] for all \\begin{$envName} occurrences

        $catcodeState = CatcodeState::IDLE;
        $pendingChar = null;

        $mostRecentCommandNodeIndex = -1;
        $newlinesSinceMostRecentCommandNode = 0;

        while ($token = $lexer->next()) {
            $rawText = substr($source, $token->start, $token->end - $token->start);

            echo "\ntoken: " . $token->type->name . " [$rawText]\n";
            echo "stack (" . count($stack) . "): " . (count($stack) < 10 ? " " : "") . "[" . implode(', ', $stack) . "]\n";
            echo "curlyStack: [" . implode(', ', array_map(fn($x) => '[' . implode(',', $x) . ']', $curlyStack)) . "]\n";
            echo "delimiters: [" . implode(', ', array_map(fn($x) => "[\"$x[0]\",$x[1],$x[2]]", $delimiterStack)) . "]\n";
            echo "beginStack: [" . implode(', ', array_map(fn($x) => "[\"$x[0]\",$x[1]]", $beginStack)) . "]\n";
            echo "mostRecent: $mostRecentCommandNodeIndex\n";

            foreach ($stack as $node) {
               if ($node instanceof ParseTreeNode) {
                    foreach ($node->getChildren() as $child) {
                        if (!($child instanceof ParseTreeNode)) {
                            throw new ParseException("Added non-child to node " . $node . "\n", 0);
                        }
                    }
                }
            }

            $this->handleCatcodeState($token, $rawText, $lexer, $catcodeState, $pendingChar);

            if ($token->type === TokenType::GROUP_OPEN) {
                $curlyStack[] = [count($stack), $mostRecentCommandNodeIndex];
                $stack[] = $token;
                $mostRecentCommandNodeIndex = -1;
                continue;
            }

            if ($this->isClosingDelimiter($token, $rawText, $delimiterStack)) {
//                if ($token->type === TokenType::GROUP_CLOSE) {
//                    $commandNodeBeforeGroupIndex = empty($curlyStack) ? -1 : end($curlyStack)[1];
//                } else {
//                    $commandNodeBeforeGroupIndex = empty($delimiterStack) ? -1 : end($delimiterStack)[2];
//                }
//                echo "commandNodeBeforeGroupIndex: " . $commandNodeBeforeGroupIndex . "\n";
//
                $this->reduceNode($stack, $token, $rawText, $delimiterStack, $beginStack, $curlyStack, $lexer);

                if ($token->type === TokenType::GROUP_CLOSE && end($stack) instanceof CommandNode) {
                    $mostRecentCommandNodeIndex = count($stack) - 1;
                } else {
                    $mostRecentCommandNodeIndex = - 1;
                }
                $newlinesSinceMostRecentCommandNode = 0;
                continue;
            }

            if ($this->isOpeningDelimiter($token, $rawText)) {
                if ($token->type === TokenType::OPT_OPEN && $mostRecentCommandNodeIndex < 0) {
                    $stack[] = new TextNode($token->lineNumber, $rawText);
                } else {
                    $delimiterStack[] = [$rawText, count($stack), $mostRecentCommandNodeIndex];
                    $stack[] = $token;
                    $mostRecentCommandNodeIndex = -1;
                }
                continue;
            }

            if ($token->type === TokenType::COMMAND) {
                if ($rawText === '\\verb' || $rawText === '\\verb*') {
                    $this->handleVerbatim($stack, $token, $rawText, $lexer);
                    $mostRecentCommandNodeIndex = -1;
                    continue;
                }
            }

            if ($token->type === TokenType::TEXT || $token->type === TokenType::OPT_OPEN) {
                $this->insertTextOrWhitespace($stack, $rawText, $token->lineNumber, false);
                $mostRecentCommandNodeIndex = -1;
                continue;
            }

            if ($token->type === TokenType::WHITESPACE) {
                $this->insertTextOrWhitespace($stack, $rawText, $token->lineNumber, true);

                if ($mostRecentCommandNodeIndex >= 0) {
                    $newlinesInWhitespace = substr_count($rawText, "\n");
                    $newlinesSinceMostRecentCommandNode += $newlinesInWhitespace;
                    if ($newlinesSinceMostRecentCommandNode > 1) {
                        $mostRecentCommandNodeIndex = -1;
                    }
                }

                continue;
            }

            $node = $this->createNodeFromToken($token, $rawText);

            if ($node instanceof CommandNode) {
                $mostRecentCommandNodeIndex = count($stack);
                $newlinesSinceMostRecentCommandNode = 0;
            } elseif (!($node instanceof CommentNode)) {
                $mostRecentCommandNodeIndex = -1;
            }

            $stack[] = $node;
        }

        $endDocumentIndex = count($stack) - 1;
        for (; $endDocumentIndex >= 0; $endDocumentIndex--) {
            $node = $stack[$endDocumentIndex];
            if ($node instanceof CommandNode &&
                $node->getName() === '\\end' &&
                $node->getChildCount() === 2 && // TODO: Watch for Whitespace- / CommandNodes
                ($child = $node->getChild(1)) instanceof ArgumentNode &&
                $child->getText() === 'document') {
                break;
            }
        }
        if ($endDocumentIndex < 0) {
            $endDocumentIndex = count($stack);
        }

        $beginDocumentIndex = 0;
        for (; $beginDocumentIndex < $endDocumentIndex; $beginDocumentIndex++) {
            $node = $stack[$beginDocumentIndex];

            if ($node instanceof CommandNode &&
                $node->getName() === '\\begin' &&
                $node->getChildCount() === 2 && // TODO: watch out for Whitespace- / CommentNodes!
                ($child = $node->getChild(1)) instanceof ArgumentNode &&
                $child->getText() === 'document') {
                break;
            }
        }
        if ($beginDocumentIndex === $endDocumentIndex) {
            $beginDocumentIndex = -1;
        }

        while (!empty($delimiterStack) && end($delimiterStack)[0] === '{') {
            $index = array_pop($delimiterStack)[1];
            $length = $endDocumentIndex - $index - 1;
            $groupNode = new UnclosedGroupNode($stack[$index]->lineNumber);
            $groupNode->addChildren(array_splice($stack, $index + 1, $length));
            array_splice($stack, $index, 1, [$groupNode]);
            $endDocumentIndex -= $length;
        }

        if (!empty($delimiterStack)) {
            [$unclosed, $index, $_] = end($delimiterStack);
            throw new ParseException("Unclosed delimiter '$unclosed'", $stack[$index]->lineNumber);
        }

        for ($i = 0; $i < $endDocumentIndex; $i++) {
            if ($i === $beginDocumentIndex) {
                continue;
            }

            $node = $stack[$i];
            if ($node instanceof CommandNode && $node->getName() === '\\begin') {
                throw new ParseException("Unclosed environment '" . $this->getEnvironmentName($node) . "'", $node->lineNumber);
            }
        }

        if ($endDocumentIndex < count($stack)) {
            if ($beginDocumentIndex > -1) {
                $documentNode = new EnvironmentNode('document', $stack[$beginDocumentIndex], $stack[$endDocumentIndex]);
                $documentNode->addChildren(array_splice($stack, $beginDocumentIndex + 1, $endDocumentIndex - $beginDocumentIndex - 1));
                array_splice($stack, $beginDocumentIndex, 2, [$documentNode]);
            }
        }

        $root = new RootNode();
        $root->addChildren($stack);
        return new ParseTree($root);
    }

    /**
     * @throws ParseException
     */
    private function handleCatcodeState(Token $token, string $raw, Lexer $lexer, CatcodeState &$state, ?int &$pendingChar): void
    {
        // Whitespace and Comments are "transparent"—they don't break the
        // assignment chain, but they also don't provide targets/values.
        if ($token->type === TokenType::WHITESPACE || $token->type === TokenType::COMMENT) {
            return;
        }

        switch ($state) {
            case CatcodeState::IDLE:
                if ($token->type === TokenType::COMMAND) {
                    if ($raw === '\\catcode') {
                        $state = CatcodeState::EXPECTING_TARGET;
                        $lexer->setAllowWhitespaceInText(false);
                    } elseif ($raw === '\\makeatletter') {
                        $lexer->setCatCode(64, 11);
                    } elseif ($raw === '\\makeatother') {
                        $lexer->setCatCode(64, 12);
                    }
                }
                break;

            /** @noinspection PhpMissingBreakStatementInspection */
            case CatcodeState::EXPECTING_TARGET:
                if ($token->type === TokenType::TEXT) {
                    $raw = ltrim($raw);
                    [$val, $consumed] = $this->parseTeXInt($raw, $token->lineNumber, $lexer->getEncoding());

                    if ($consumed > 0) {
                        $pendingChar = $val;
                        $state = CatcodeState::EXPECTING_VALUE_OR_EQUALS;
                        $raw = ltrim(substr($raw, $consumed));
                        // falls through
                    } else {
                        $state = CatcodeState::IDLE;
                        break;
                    }
                } else {
                    // Any non-text token where a number was expected resets the state
                    $state = CatcodeState::IDLE;
                    break;
                }

            case CatcodeState::EXPECTING_VALUE_OR_EQUALS:
            case CatcodeState::EXPECTING_VALUE:
                if ($token->type === TokenType::COMMAND) {
                    $val = match ($raw) {
                        '\\active' => 13,
                        '\\letter' => 11,
                        '\\other' => 12,
                        default => throw new ParseException("Unexpected command '$raw' during \\catcode assignment", $token->lineNumber)
                    };

                    $lexer->setCatCode($pendingChar, $val);
                } elseif ($token->type === TokenType::TEXT) {
                    if ($state == CatcodeState::EXPECTING_VALUE_OR_EQUALS) {
                        if (str_starts_with($raw, '=')) {
                            $state = CatcodeState::EXPECTING_VALUE;
                            $raw = ltrim(substr($raw, 1));
                            if ($raw == '') {
                                return;
                            }
                        }
                    }

                    if ($raw == '') {
                        return;
                    }

                    [$val, $consumed] = $this->parseTeXInt($raw, $token->lineNumber, $lexer->getEncoding());
                    if ($consumed > 0) {
                        $lexer->setCatCode($pendingChar, $val);
                    }
                }
                $state = CatcodeState::IDLE;
                break;
        }

        if ($state == CatcodeState::IDLE) {
            $lexer->setAllowWhitespaceInText(true);
        }
    }

    private function isOpeningDelimiter(Token $token, string $raw): bool
    {
        if ($token->type === TokenType::COMMAND) {
            return $raw == '\\(' || $raw == '\\[';
        }

        // We only treat these as openers.
        // Toggles are handled by the loop logic (if it wasn't a closer, it's an opener).
        return in_array($token->type, [
            TokenType::GROUP_OPEN,
            TokenType::OPT_OPEN,
            TokenType::MATH_TOGGLE
        ], true);
    }

    private function isClosingDelimiter(Token $token, string $raw, array $delimiterStack): bool
    {
        if ($token->type === TokenType::GROUP_CLOSE || $token->type === TokenType::OPT_CLOSE) {
            return true;
        }

        if ($token->type === TokenType::COMMAND) {
            return $raw == '\\)' || $raw == '\\]';
        }

        if ($token->type === TokenType::MATH_TOGGLE) {
            for ($i = count($delimiterStack) - 1; $i >= 0; $i--) {
                $delimiter = $delimiterStack[$i][0];

                if ($delimiter === $raw) {
                    return true;
                } elseif ($delimiter !== '[') {
                    break;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @throws ParseException
     */
    private function reduceNode(array &$stack, Token $closer, string $raw, array &$delimiterStack, array &$beginStack, array &$curlyStack, Lexer $lexer): void
    {
        if ($closer->type === TokenType::GROUP_CLOSE) {
            if (empty($curlyStack)) {
                throw new ParseException("Unmatched '}'", $closer->lineNumber);
            }

            [$openerStackIndex, $commandNodeIndex] = array_pop($curlyStack);

            $opener = $stack[$openerStackIndex];
            $this->unstack($delimiterStack, $stack, null, $openerStackIndex + 1);
            $this->unstack($beginStack, $stack, null, $openerStackIndex + 1);

            if ($commandNodeIndex < 0) {
                $groupNode = new GroupNode($opener->lineNumber);
                $groupNode->addChildren(array_splice($stack, $openerStackIndex + 1));
                array_splice($stack, $openerStackIndex, 1, [$groupNode]);
            } else {
                [$command, $argNode] = $this->addArgumentToCommandNode($stack[$openerStackIndex], false, $stack, $openerStackIndex, $commandNodeIndex);

                $envName = $this->getEnvironmentName($command);

                if ($command->getName() == '\\begin') {
                    if (in_array($envName, $this->rawEnvironments, true)) {
                        array_pop($stack);
                        $this->handleRawEnvironment($stack, $command, $envName, $lexer);
                    } else {
                        $beginStack[] = [$envName, $commandNodeIndex];
                    }
                } elseif ($command->getName() == '\\end') {
                    array_pop($stack);
                    $this->reduceEnvironment($stack, $command, $envName, $curlyStack, $beginStack, $delimiterStack);
                } elseif ($command->getName() === '\\usepackage') {
                    $this->detectInputEnc($argNode, $lexer);
                }
            }
        } elseif ($closer->type === TokenType::OPT_CLOSE) {
            [$lastCurlyIndex, $lastBeginIndex, $openerStackIndex, $mostRecentCommandNodeIndex] = $this->getStackIndices($curlyStack, $beginStack, $delimiterStack, '[');

            if ($openerStackIndex === -1 ||
                $mostRecentCommandNodeIndex < 0 ||
                $lastCurlyIndex > $openerStackIndex ||
                $lastBeginIndex > $openerStackIndex) {
                $this->insertTextOrWhitespace($stack, $raw, $closer->lineNumber, false);
            } else {
                $this->unstack($delimiterStack, $stack, null, $openerStackIndex + 1);
                $this->unstack($beginStack, $stack, null, $openerStackIndex + 1);
                $this->addArgumentToCommandNode($stack[$openerStackIndex], true, $stack, $openerStackIndex, $mostRecentCommandNodeIndex);
                array_pop($delimiterStack);
            }
        } else if ($raw === '$' || $raw === '$$') {
            [$lastCurlyIndex, $lastBeginIndex, $openerStackIndex, $_] = $this->getStackIndices($curlyStack, $beginStack, $delimiterStack, $raw);

            if ($lastCurlyIndex > $openerStackIndex ||
                $lastBeginIndex > $openerStackIndex) {
                $delimiterStack[] = [$raw, count($stack), -1];
                $stack[] = $closer;
            } else {
                $this->unstack($delimiterStack, $stack, null, $openerStackIndex + 1);
                $this->unstack($beginStack, $stack, null, $openerStackIndex + 1);

                $this->finalizeDelimiters($stack, $openerStackIndex, $raw, $closer, $raw);
                array_pop($delimiterStack);
            }
        } else {
            $openerRaw = match ($raw) {
                '\\]' => '\\[',
                '\\)' => '\\(',
            };

            [$lastCurlyIndex, $lastBeginIndex, $openerStackIndex, $_] = $this->getStackIndices($curlyStack, $beginStack, $delimiterStack, $openerRaw);

            if ($openerStackIndex < 0 ||
                $lastCurlyIndex > $openerStackIndex ||
                $lastBeginIndex > $openerStackIndex) {
                if ($openerStackIndex >= 0) {
                    $stack[$openerStackIndex] = $this->createNodeFromToken($stack[$openerStackIndex], $openerRaw);
                }
                $stack[] = $this->createNodeFromToken($closer, $raw);
                array_pop($delimiterStack);
            } else {
                $this->unstack($delimiterStack, $stack, null, $openerStackIndex + 1);
                $this->unstack($beginStack, $stack, null, $openerStackIndex + 1);

                $this->finalizeDelimiters($stack, $openerStackIndex, $openerRaw, $closer, $raw);
                array_pop($delimiterStack);
            }
        }
    }

    /**
     * @param array $curlyStack
     * @param array $beginStack
     * @param array $delimiterStack
     * @param string $delimiter
     * @return array
     */
    private function getStackIndices(array $curlyStack, array $beginStack, array $delimiterStack, string|null $delimiter): array
    {
        $lastCurlyIndex = empty($curlyStack) ? -1 : end($curlyStack)[0];
        $lastBeginIndex = empty($beginStack) ? -1 : end($beginStack)[1];
        $openerStackIndex = -1;
        $mostRecentCommandNodeIndex = -1;

        for ($i = count($delimiterStack) - 1; $i >= 0; $i--) {
            [$delimiterStackRawText, $openerStackIndex, $mostRecentCommandNodeIndex] = $delimiterStack[$i];
            if ($delimiterStackRawText === $delimiter) {
                break;
            }
        }
        return [$lastCurlyIndex, $lastBeginIndex, $openerStackIndex, $mostRecentCommandNodeIndex];
    }

    /**
     * @param array $stack
     * @param mixed $openerStackIndex
     * @param string $openerRaw
     * @param Token $closer
     * @param string $closerRaw
     * @return array
     */
    public function finalizeDelimiters(array &$stack, mixed $openerStackIndex, string $openerRaw, Token $closer, string $closerRaw): void
    {
        $opener = $stack[$openerStackIndex];

        $openingNode = ($opener->type === TokenType::COMMAND) ? new CommandNode($opener->lineNumber, $openerRaw) : new TextNode($opener->lineNumber, $openerRaw);
        $closingNode = ($closer->type === TokenType::COMMAND) ? new CommandNode($closer->lineNumber, $closerRaw) : new TextNode($closer->lineNumber, $closerRaw);
        $mathNode = new MathNode($opener->lineNumber, $openingNode, $closingNode);
        $mathNode->addChildren(array_splice($stack, $openerStackIndex + 1));
        array_splice($stack, $openerStackIndex, 1, [$mathNode]);
    }

    /**
     * @throws ParseException
     */
    private function unstack(array &$auxStack, array &$stack, string|null $stop, int $minStackIndex = 0): int
    {
        while (!empty($auxStack)) {
            $top = array_pop($auxStack);
            $raw = $top[0];
            $stackIndex = $top[1];

            if ($raw === $stop) {
                return $stackIndex;
            } elseif ($stackIndex < $minStackIndex) {
                $auxStack[] = $top;
                return $minStackIndex;
            }

            $stackElement = $stack[$stackIndex];

            if ($stackElement instanceof Token) {
                $lineNumber = $stackElement->lineNumber;

                if (in_array($raw, ['$', '$$', '\\]', '\\)'])) {
                    $stack[$stackIndex] = new CommandNode($lineNumber, $raw);
                } else {
                    $this->insertTextOrWhitespace($stack, $raw, $lineNumber, false, $stackIndex);
                }
            }
        }

        return -1;
    }

    /**
     * @throws ParseException
     */
    private function reduceEnvironment(array &$stack, CommandNode $endNode, string $envName, array $curlyStack, array &$beginStack, array &$delimiterStack): void
    {
        [$lastCurlyIndex, $lastBeginIndex, $_, $_] = $this->getStackIndices($curlyStack, $beginStack, $delimiterStack, null);

        if ($lastBeginIndex === -1 || $lastBeginIndex < $lastCurlyIndex) {
            $stack[] = $endNode;
        } else {
            $this->unstack($delimiterStack, $stack, null, $lastBeginIndex);
            $this->unstack($beginStack, $stack, null, $lastBeginIndex);

            $beginNode = $stack[$lastBeginIndex];

            if (in_array($envName, $this->mathEnvironments)) {
                $envNode = new MathEnvironmentNode($envName, $beginNode, $endNode);
            } else {
                $envNode = new EnvironmentNode($envName, $beginNode, $endNode);
            }

            $envNode->addChildren(array_splice($stack, $lastBeginIndex + 1));
            array_splice($stack, $lastBeginIndex, 1, [$envNode]);
        }
    }

    private function getEnvironmentName(CommandNode $command): ?string
    {
        foreach ($command->getChildren() as $child) {
            if ($child instanceof ArgumentNode && !$child->isOptional) {
                return $child->getText(trim: true);
            }
        }

        return null;
    }

    /**
     * @throws ParseException
     */
    private function handleVerbatim(array &$stack, Token $token, string $rawText, Lexer $lexer): void
    {
        $delim = $lexer->consumeRawChar()[0];

        if ($delim === null) {
            throw new ParseException("Unexpected end of input after $rawText", $token->lineNumber);
        }

        $body = $lexer->consumeRawUntil($delim)[0];

        $stack[] = new VerbNode($token->lineNumber, $body, $delim);
    }

    /**
     * @throws ParseException
     */
    private function handleRawEnvironment(array &$stack, CommandNode $beginCommand, string $envName, Lexer $lexer): void
    {
        $closingMarker = "\\end{{$envName}}";
        [$rawText, $lineNumber] = $lexer->consumeRawUntil($closingMarker);

        if ($rawText === null) {
            throw new ParseException("Unexpected end of input after $rawText", $lineNumber);
        }

        $child = new TextNode($beginCommand->lineNumber, $rawText);

        $endCommand = new CommandNode($lineNumber, '\\end');
        $endArgument = new ArgumentNode($lineNumber, false);
        $endArgument->addChild(new TextNode($lineNumber, $envName));
        $endCommand->addChild($endArgument);

        $envNode = new EnvironmentNode($envName, $beginCommand, $endCommand);
        $envNode->addChild($child);

        $stack[] = $envNode;
    }

    /**
     * @throws ParseException
     */
    private function detectInputEnc(ArgumentNode $node, Lexer $lexer): void
    {
        $packages = array_map('trim', explode(',', $node->getText(true)));

        if (!in_array('inputenc', $packages, true)) {
            return;
        }

        $cmd = $node->parent;
        foreach ($cmd->getChildren() as $sibling) {
            if ($sibling instanceof ArgumentNode && $sibling->isOptional) {
                // Just pass the raw trimmed text; Lexer::setEncoding handles the rest
                $lexer->setEncoding($sibling->getText(true));
                break;
            }
        }
    }

    private function createNodeFromToken(Token $token, string $rawText): CommandNode|CommentNode|TextNode|WhitespaceNode
    {
        $lineNumber = $token->lineNumber;

        return match ($token->type) {
            TokenType::COMMAND => new CommandNode($lineNumber, $rawText),
            TokenType::COMMENT => new CommentNode($lineNumber, $rawText),
            // MATH_TOGGLE ($ or $$) can be pushed as a TextNode initially,
            // then handled by reduceNode.
            TokenType::ALIGN_TAB,
            TokenType::MATH_TOGGLE => new TextNode($lineNumber, $rawText),
            default => new ParseException("Unexpected token type: {$token->type->name}", $lineNumber),
        };
    }

    /**
     * @param mixed $opener
     * @param array $stack
     * @param mixed $openerStackIndex
     * @param mixed $mostRecentCommandNodeIndex
     * @return array
     */
    public function addArgumentToCommandNode(Token $opener, bool $isOptional, array &$stack, mixed $openerStackIndex, mixed $mostRecentCommandNodeIndex): array
    {
        $argNode = new ArgumentNode($opener->lineNumber, $isOptional);
        $argNode->addChildren(array_splice($stack, $openerStackIndex + 1));
        array_pop($stack);

        $command = $stack[$mostRecentCommandNodeIndex];
        $command->addChildren(array_splice($stack, $mostRecentCommandNodeIndex + 1));
        $command->addChild($argNode);

        return [$command, $argNode];
    }

    private function getLastContentIndex(array $stack, int $offset = -1): int
    {
        if ($offset < 0) {
            $offset += count($stack);
        }

        for ($i = $offset; $i >= 0; $i--) {
            $node = $stack[$i];
            if (!$node instanceof WhitespaceNode && !$node instanceof CommentNode) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @throws ParseException
     */
    private function insertTextOrWhitespace(array &$stack, string $content, int $lineNumber, bool $isWhitespace, ?int $stackIndex = null): void
    {
        if ($content === '') return;

        if ($stackIndex === null) {
            $stackIndex = count($stack);
        } else if ($stackIndex < 0 || $stackIndex > count($stack)) {
            throw new ParseException("Internal error: invalid stack state.", $lineNumber);
        }

        $left = $stackIndex > 0 ? $stack[$stackIndex - 1] : null;
        $right = $stackIndex + 1 < count($stack) ? $stack[$stackIndex + 1] : null;

        if ($isWhitespace) {
            if ($left instanceof WhitespaceNode) {
                if ($right instanceof TextNode) {
                    array_splice($stack, $stackIndex - 1, 2);
                    $right->content = $left->content . $content . $right->content;
                } else {
                    array_splice($stack, $stackIndex, 1);
                    $left->content .= $content;
                }
            } elseif ($left instanceof TextNode) {
                if ($right instanceof TextNode) {
                    array_splice($stack, $stackIndex, 2);
                    $left->content .= $content . $right->content;
                } else {
                    array_splice($stack, $stackIndex, 1);
                    $left->content .= $content;
                }
            } elseif ($right instanceof TextNode) {
                array_splice($stack, $stackIndex, 1);
                $right->content = $content . $right->content;
            } else {
                $stack[$stackIndex] = new WhitespaceNode($lineNumber, $content);
            }
        } else {
            if ($left instanceof WhitespaceNode) {
                if ($right instanceof WhitespaceNode) {
                    array_splice($stack, $stackIndex, 2);
                    $stackIndex--;
                    $stack[$stackIndex] = new TextNode($lineNumber, $left->content . $content . $right->content);
                } elseif ($right instanceof TextNode) {
                    array_splice($stack, $stackIndex - 1, 2);
                    $right->content = $left->content . $content . $right->content;
                } else {
                    array_splice($stack, $stackIndex, 1);
                    $stackIndex--;
                    $stack[$stackIndex] = new TextNode($lineNumber, $left->content . $content);
                }
            } elseif ($left instanceof TextNode) {
                if ($right instanceof TextNode) {
                    array_splice($stack, $stackIndex, 2);
                    $left->content .= $content . $right->content;
                } else {
                    array_splice($stack, $stackIndex, 1);
                    $left->content .= $content;
                }
            } elseif ($right instanceof TextNode) {
                array_splice($stack, $stackIndex, 1);
                $right->content = $content . $right->content;
            } else {
                $stack[$stackIndex] = new TextNode($lineNumber, $content);
            }
        }
    }

    /**
     * @throws ParseException
     */
    private function parseTeXInt(string $input, int $lineNumber, string $encoding): array
    {
        if ($input === '') {
            return [null, 0];
        }

        $firstChar = $input[0];

        // 1. Decimal
        if (is_numeric($firstChar)) {
            if (preg_match('/^\d+/', $input, $matches)) {
                return [(int)$matches[0], strlen($matches[0])];
            }
        }

        // 2. Prefixed numbers
        if (in_array($firstChar, ["'", '"', '`'], true)) {
            if (strlen($input) < 2) {
                return [null, 0];
            }

            $remainder = substr($input, 1);

            switch ($firstChar) {
                case '"': // Hexadecimal
                    if (preg_match('/^[0-9a-fA-F]+/', $remainder, $matches)) {
                        return [hexdec($matches[0]), strlen($matches[0]) + 1];
                    }
                    throw new ParseException("Invalid hex character in \\catcode assignment", $lineNumber);

                case "'": // Octal
                    if (preg_match('/^[0-7]+/', $remainder, $matches)) {
                        return [octdec($matches[0]), strlen($matches[0]) + 1];
                    }
                    throw new ParseException("Invalid octal character in \\catcode assignment", $lineNumber);

                case '`': // Character Literal
                    if (preg_match('/^(\\\\?)([a-zA-Z@]+|.)/u', $remainder, $matches)) {
                        $hasBackslash = $matches[1] !== '';
                        $targetChar = $matches[2][0];

                        $code = ($encoding === 'utf8')
                            ? mb_ord($hasBackslash ? $targetChar : $matches[2], 'UTF-8')
                            : ord($targetChar);

                        return [$code, strlen($matches[0]) + 1];
                    }
                    break;
            }
        }

        return [null, 0];
    }
}
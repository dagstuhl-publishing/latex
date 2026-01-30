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
        'comment'
    ];

    /**
     * @throws ParseException
     */
    public function parse(string $source): ParseTree
    {
        $lexer = new Lexer($source);
        $stack = [];
        $delimiterStack = []; // Local scope tracking

        $catcodeState = CatcodeState::IDLE;
        $pendingChar = null;

        while ($token = $lexer->next()) {
            $rawText = substr($source, $token->start, $token->end - $token->start);

            $this->handleCatcodeState($token, $rawText, $lexer, $catcodeState, $pendingChar);

            // 1. Check for reduction first
            if ($this->isClosingDelimiter($stack, $token, $rawText, $delimiterStack)) {
                $this->reduceNode($stack, $token, $rawText, $delimiterStack, $lexer);
                continue;
            }

            // 2. Check for shifting/opening
            if ($this->isOpeningDelimiter($token, $rawText)) {
                if ($token->type === TokenType::OPT_OPEN) {
                    $contentIndex = $this->getLastContentIndex($stack);
                    $contentNode = $contentIndex !== -1 ? $stack[$contentIndex] : null;

                    if (!($contentNode instanceof CommandNode) ||
                        (strlen($contentNode->getName()) === 2 && $lexer->getCatcode(ord($contentNode->getName()[1])) !== 12)) {
                        $this->insertTextOrWhitespace($stack, $rawText, $token->lineNumber, false);
                        continue;
                    }
                }

                $delimiterStack[] = [$rawText, count($stack)];
                $stack[] = $token;
                continue;
            }

            // 3. Special raw command handling
            if ($token->type === TokenType::COMMAND) {
                if ($rawText === '\verb' || $rawText === '\verb*') {
                    $this->handleVerbatim($stack, $token, $rawText, $lexer);
                    continue;
                }
            }

            if ($token->type === TokenType::TEXT) {
                $this->insertTextOrWhitespace($stack, $rawText, $token->lineNumber, false);
                continue;
            }

            if ($token->type === TokenType::WHITESPACE) {
                $this->insertTextOrWhitespace($stack, $rawText, $token->lineNumber, true);
                continue;
            }

            // 4. Default: Shift a standard closed node
            $stack[] = $this->createNodeFromToken($token, $rawText);
        }

        if (!empty($delimiterStack)) {
            [$unclosed, $index] = end($delimiterStack);
            throw new ParseException("Unclosed delimiter '$unclosed'", $stack[$index]->lineNumber);
        }

        foreach ($stack as $node) {
            if ($node instanceof CommandNode && $node->getName() === '\begin') {
                $envName = $this->getEnvironmentName($node);

                throw new ParseException("Unclosed " . ($envName ? "environment '$envName'" : "\begin"), $node->lineNumber);
            }
        }

        $root = new RootNode();
        $root->addChildren($stack);
        return new ParseTree($root);
    }

    /**
     * @throws ParseException
     */
    private function handleCatcodeState(Token $token, string $raw, Lexer $lexer, CatcodeState &$state, ?int &$pendingChar): void {
        // Whitespace and Comments are "transparent"—they don't break the
        // assignment chain, but they also don't provide targets/values.
        if ($token->type === TokenType::WHITESPACE || $token->type === TokenType::COMMENT) {
            return;
        }

        switch ($state) {
            case CatcodeState::IDLE:
                if ($token->type === TokenType::COMMAND) {
                    if ($raw === '\catcode') {
                        $state = CatcodeState::EXPECTING_TARGET;
                        $lexer->setAllowWhitespaceInText(false);
                    } elseif ($raw === '\makeatletter') {
                        $lexer->setCatCode(64, 11);
                    } elseif ($raw === '\makeatother') {
                        $lexer->setCatCode(64, 12);
                    }
                }
                break;

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
                        '\active' => 13,
                        '\letter' => 11,
                        '\other' => 12,
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
            return $raw == '\(' || $raw == '\[';
        }

        // We only treat these as openers.
        // Toggles are handled by the loop logic (if it wasn't a closer, it's an opener).
        return in_array($token->type, [
            TokenType::GROUP_OPEN,
            TokenType::OPT_OPEN,
            TokenType::MATH_TOGGLE
        ], true);
    }

    /**
     * @throws ParseException
     */
    private function isClosingDelimiter(array &$stack, Token $token, string $raw, array &$delimiterStack): bool
    {
        if ($token->type === TokenType::GROUP_CLOSE || $token->type === TokenType::OPT_CLOSE) {
            return true;
        }

        if ($token->type === TokenType::COMMAND) {
            return $raw == '\)' || $raw == '\]';
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
     * This is the main method that creates tree node from the stack when certain tokens are encountered.
     *
     * @throws ParseException
     */
    private function reduceNode(array &$stack, Token $closer, string $raw, array &$delimiterStack, $lexer): void
    {
        $openerRaw = null;

        if (empty($delimiterStack)) {
            $isMatch = false;
        } else {
            $openerRaw = end($delimiterStack)[0];

            if ($raw !== ']') {
                $leftBracketStackIndices = [];

                while ($openerRaw === '[') {
                    $leftBracketStackIndices[] = end($delimiterStack)[1];
                    array_pop($delimiterStack);
                    $openerRaw = empty($delimiterStack) ? null : end($delimiterStack)[0];
                }

                for ($j = 0; $j < count($leftBracketStackIndices); $j++) {
                    $stackIndex = $leftBracketStackIndices[$j];
                    $this->insertTextOrWhitespace($stack, '[', $stack[$stackIndex]->lineNumber, false, $stackIndex);
                }
            }

            $isMatch = match($raw) {
                '}'       => $openerRaw === '{',
                ']'       => $openerRaw === '[',
                '\]'      => $openerRaw === '\[',
                '\)'      => $openerRaw === '\(',
                '$', '$$' => $openerRaw === $raw,
                default   => false
            };
        }

        if ($isMatch) {
            // --- 1. Perform Reduction ---
            $stackIndex = array_pop($delimiterStack)[1];
            $children = array_splice($stack, $stackIndex + 1);
            $opener = array_pop($stack);

            if (in_array($raw, ['$', '$$', '\]', '\)'])) {
                $openingNode = ($opener->type === TokenType::COMMAND) ? new CommandNode($opener->lineNumber, $openerRaw) : new TextNode($opener->lineNumber, $openerRaw);
                $closingNode = ($closer->type === TokenType::COMMAND) ? new CommandNode($closer->lineNumber, $raw) : new TextNode($closer->lineNumber, $raw);
                $mathNode = new MathNode($opener->lineNumber, $openingNode, $closingNode);
                $mathNode->addChildren($children);
                $stack[] = $mathNode;
            } else {  // $raw is ']' or '}'
                $isOptional = ($opener->type === TokenType::OPT_OPEN);
                $contentIndex = $this->getLastContentIndex($stack);
                $contentNode = $contentIndex !== -1 ? $stack[$contentIndex] : null;
                $isArgument = $isOptional || (
                    $contentNode instanceof CommandNode && (
                        strlen($contentNode->getName()) > 2 || (
                            strlen($contentNode->getName()) > 1 &&
                            $lexer->getCatcode(ord($contentNode->getName()[1])) === 12
                        )
                    ));

                if ($isArgument) {
                    $command = $stack[$contentIndex];
                    $command->addChildren(array_splice($stack, $contentIndex + 1));

                    $argNode = new ArgumentNode($opener->lineNumber, $isOptional);
                    $argNode->addChildren($children);
                    $command->addChild($argNode);

                    if (!$isOptional) {
                        $envName = $this->getEnvironmentName($command);

                        if ($command->getName() == '\begin' &&
                            in_array($envName, $this->rawEnvironments, true)) {
                            array_pop($stack);
                            $this->handleRawEnvironment($stack, $command, $envName, $lexer);
                        } else if ($command->getName() == '\end') {
                            array_pop($stack);
                            try {
                                $this->reduceEnvironment($stack, $command, $delimiterStack);
                            } catch (ParseException $e) {
                                // if \end cannot be matched we just leave it in as an ordinary CommandNode
                                $stack[] = $command;
                            }
                        } else if ($command->getName() === '\usepackage') {
                            $this->detectInputEnc($argNode, $lexer);
                        }
                    }
                } else {
                    // Brackets won't reach here anymore because they aren't pushed to delimiterStack
                    // unless there is a CommandNode. Only {} groups fall through here.
                    $groupNode = new GroupNode($opener->lineNumber);
                    $groupNode->addChildren($children);
                    $stack[] = $groupNode;
                }
            }
        } else {
            if ($raw === ']') {
                $this->insertTextOrWhitespace($stack, ']', $closer->lineNumber, false);
            } else {
                throw new ParseException("Unmatched closing delimiter '$raw'", $closer->lineNumber);
            }
        }
    }

    /**
     * @throws ParseException
     */
    private function reduceEnvironment(array &$stack, CommandNode $endNode, array &$delimiterStack): void
    {
        $envName = $this->getEnvironmentName($endNode);

        if ($envName === null) {
            throw new ParseException("Missing environment name in \\end", $endNode->lineNumber);
        }

        // Use a pointer to simulate the top of the stack without mutating the reference
        $j = count($delimiterStack) - 1;
        $delimiterIndex = ($j >= 0) ? $delimiterStack[$j][1] : -1;

        $leftBracketStackIndices = [];
        $foundIndex = -1;

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($i === $delimiterIndex) {
                $delimiterRaw = $delimiterStack[$j][0];
                if ($delimiterRaw === '[') {
                    $leftBracketStackIndices[] = $delimiterIndex;

                    // Move the pointer down instead of popping
                    $j--;
                    $delimiterIndex = ($j >= 0) ? $delimiterStack[$j][1] : -1;
                    continue;
                } else {
                    // If it's a delimiter but not '[', we stop searching
                    break;
                }
            }

            $node = $stack[$i];
            if ($node instanceof CommandNode
                && $node->getName() === '\begin'
                && $this->getEnvironmentName($node) === $envName) {
                $foundIndex = $i;
                break;
            }
        }

        if ($foundIndex === -1) {
            // Since we only used $j and local variables, $delimiterStack is still intact here
            throw new ParseException("No matching opener found for $envName environment.", $endNode->lineNumber);
        }

        // Success: Commit the changes to the stack by removing the elements we "passed over"
        // $j is the index of the last element to KEEP.
        array_splice($delimiterStack, $j + 1);

        foreach ($leftBracketStackIndices as $stackIndex) {
            $this->insertTextOrWhitespace($stack, '[', $stack[$stackIndex]->lineNumber, false, $stackIndex);
        }

        $body = array_splice($stack, $foundIndex + 1);
        $beginNode = array_pop($stack);

        if (in_array($envName, $this->mathEnvironments)) {
            $envNode = new MathEnvironmentNode($beginNode->lineNumber, $envName, $beginNode, $endNode);
        } else {
            $envNode = new EnvironmentNode($beginNode->lineNumber, $envName, $beginNode, $endNode);
        }

        $envNode->addChildren($body);
        $stack[] = $envNode;
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

        $endCommand = new CommandNode($lineNumber,'\end');
        $endArgument = new ArgumentNode($lineNumber, false);
        $endArgument->addChild(new TextNode($lineNumber, $envName));
        $endCommand->addChild($endArgument);

        $envNode = new EnvironmentNode($beginCommand->lineNumber, $envName, $beginCommand, $endCommand);
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
            TokenType::COMMAND    => new CommandNode($lineNumber, $rawText),
            TokenType::COMMENT    => new CommentNode($lineNumber, $rawText),
            // MATH_TOGGLE ($ or $$) can be pushed as a TextNode initially,
            // then handled by reduceNode.
            TokenType::ALIGN_TAB,
            TokenType::MATH_TOGGLE => new TextNode($lineNumber, $rawText),
            default => new ParseException("Unexpected token type: {$token->type->name}", $lineNumber),
        };
    }

    private function getLastContentIndex(array $stack): int
    {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
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
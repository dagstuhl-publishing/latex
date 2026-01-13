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

use Dagstuhl\Latex\Parser\TreeNodes\CommandNode;
use Dagstuhl\Latex\Parser\TreeNodes\ArgumentNode;

function findCommandNode($root, string $str): ?ParseTreeNode
{
    if ($root instanceof CommandNode &&
        $root->name === $str) {
        return $root;
    }

    $children = $root->getChildren();
    foreach ($children as $child) {
        if ($child !== null) {
            $node = findCommandNode($child, $str);
            if ($node !== null) {
                return $node;
            }
        }
    }

    return null;
}

$parser = new LatexParser();
$source = isset($argv[1]) ? $argv[1] : '';
$macroName = isset($argv[2]) ? $argv[2] : '';
$root = $parser->parse($source);

if ($macroName !== '') {
    $macroNode = findCommandNode($root, $macroName);

    $arguments = array_filter($macroNode->getChildren(), fn ($child) => $child instanceof ArgumentNode);
    $attributes = [
        'name' => $macroName,
        'options' => array_filter($arguments, fn ($node) => $node->isOptional),
        'arguments' => array_filter($arguments, fn ($node) => !$node->isOptional),
        'snippet' => $macroNode->toLatex()
    ];

    var_dump($attributes);
}

echo $root->toTreeString() . "\n";


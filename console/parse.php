<?php

namespace Dagstuhl\Latex\Parser;

require __DIR__ . '/../vendor/autoload.php';

use Dagstuhl\Latex\Parser\TreeNodes\CommandNode;
use phpDocumentor\Reflection\Types\True_;

function findCommandNode($root, string $str): ?ParseTreeNode
{
    if ($root instanceof CommandNode &&
        $root->getName() === $str) {
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

function printUsageInfo()
{
    echo "USAGE: php parse.php <source-string>\n";
    echo "       php parse.php -f <filename>\n";
}

function generateInput($filenames, $sourceStrings) {
    if (!is_array($filenames)) {
        $filenames = [$filenames];
    }

    $max = 80;
    $lprev = 0;

    if (!empty($filenames)) {
        $needsTrimming = array_reduce(array_map(fn($f) => strlen($f) > $max, $filenames), fn($a, $b) => $a || $b, false);
        $prefix = substr($filenames[0], 0, strrpos($filenames[0], '/'));
        $lprev = strlen($prefix);
        $lpost = 0;
        if ($needsTrimming) {
            foreach ($filenames as $filename) {
                $lpost = max($lpost, strlen($filename) - strrpos($filename, '/'));

                for ($i = 0; $i < $lprev; $i++) {
                    if (substr($filename, $i, 1) !== substr($prefix, $i, 1)) {
                        $lprev = $i;
                        break;
                    }
                }
            }
        }
    }

    if ($lprev === 0) {
        $lprev = $max / 2 - 3;
        $lpost = ($max + 1) / 2;
    } elseif ($lprev > $max && $lpost < $max) {
        $lprev = $max - $lpost;
    } else {
        $lpost = $max - $lprev - 3;
    }

    foreach ($filenames as $filename) {
        $label = strlen($filename) > $max ? substr($filename, 0, $lprev) . "..." . substr($filename, -$lpost, $lpost) : $filename;
        $source = file_exists($filename) ? file_get_contents($filename) : null;
        yield [$label, $source];
    }

    foreach ($sourceStrings as $source) {
        $label = strlen($source) > $max ? substr($source, 0, $lprev) . "..." . substr($source, -$lpost, $lpost) : $source;
        yield [$label, $source];
    }
}

// Define the expected options
$shortopts  = "hf:pqr:mx"; // h (help), f: (file requires value), v (verbose flag)
$longopts   = [
    "help",      // No value
    "file:",     // Value required
    "print",
    "quiet",
    "regex",
    "mute",
    "xemlock"
];

$rest_index = [];
$options = getopt($shortopts, $longopts, $rest_index);
$nonOptionArgs = array_slice($argv, $rest_index);

$verbosity = array_key_exists('m', $options) ? 0 : (array_key_exists('q', $options) ? 1 : 2);
$mine = !array_key_exists('x', $options);

$exitStatus = 0;

$parser = $mine ? new LatexParser() : new \PhpLatex_Parser();
if (!$mine) {
    $parser->addCommand(
        '\documentclass',
        array(
            // number of arguments
            'numArgs' => 1,
            // number of optional arguments, default 0
            'numOptArgs' => 1,
            // mode this command is valid in, can be: 'both', 'math', 'text'
            'mode' => 'text',
            // whether command arguments should be parsed, or handled as-is
            'parseArgs' => false,
            // whether command allows a starred variant
            'starred' => false,
        )
    );
}

foreach (generateInput(array_key_exists('f', $options) ? $options['f'] : [], $nonOptionArgs) as [$label, $source]) {
    if ($verbosity > 0) {
        echo "$label: ";
    }

    if ($source === null) {
        if ($verbosity > 0) {
            echo "not found\n";
        }
        continue;
    }

    try {
        $tree = $parser->parse($source);
        $root = $tree->root;
        if ($verbosity == 2) {
            echo "\n";
            if ($mine) {
                echo $root->toTreeString() . "\n";

                if (array_key_exists('p', $options)) {
                    echo $root->toLatex() . "\n";
                }

                if (array_key_exists('r', $options)) {
                    echo "---------\n";
                    $parseTree = new ParseTree($root, $source);
                    $regex = $options['r'];

                    $matches = [];
                    $parseTree->preg_match($regex, $matches);

                    if (empty($matches)) {
                        echo "Regular expression: no match\n";
                    } else {
                        echo $matches[0] . "\n";
                    }
                }
            } else {
                \PhpLatex_Utils_TreeDebug::debug($root);
                $latex = \PhpLatex_Renderer_Abstract::toLatex($root);
                echo $latex;
            }
        } elseif ($verbosity == 1) {
            echo "parsed successfully.\n";
        }
    } catch (\Exception $e) {
        if ($verbosity == 2) {
            echo $e->getMessage() . "\n";
        } elseif ($verbosity == 1) {
            echo "parse failed.\n";
        }
        $exitStatus = 1;
    }
}


exit($exitStatus);



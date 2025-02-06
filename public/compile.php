<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

use Dagstuhl\Latex\Compiler\LatexCompiler;
use Dagstuhl\Latex\LatexStructures\LatexFile;


header('Content-type: text/plain; charset=UTF-8');

$path = $_GET['path'];
$mode = $_GET['mode'] ?? 'full';

$latexFile = new LatexFile($path);
$latexCompiler = new LatexCompiler($latexFile);

if ($mode === 'version') {
    echo $latexCompiler->getLatexVersion();
    exit();
}

$latexCompiler->compile([ 'mode' => $mode ]);

echo implode("\n", $latexCompiler->getProfileOutput());

exit();
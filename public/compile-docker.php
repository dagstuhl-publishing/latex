<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

use Dagstuhl\Latex\Compiler\BuildProfiles\DockerLatex\DockerLatexProfile;
use Dagstuhl\Latex\Compiler\LatexCompiler;
use Dagstuhl\Latex\LatexStructures\LatexFile;


header('Content-type: text/plain; charset=UTF-8');

$path = $_GET['path'];
$mode = $_GET['mode'] ?? 'full';

$latexFile = new LatexFile($path);
$latexCompiler = new LatexCompiler($latexFile, new DockerLatexProfile());

if ($mode === 'version') {
    echo $latexCompiler->getLatexVersion();
    exit();
}

$latexCompiler->compile([ 'mode' => $mode ]);

var_dump(
    $latexCompiler->getProfileOutput(),
    $latexCompiler->getLatexExitCode(),
    $latexCompiler->getLatexLog(),
    $latexCompiler->getBibTexLog()
);

exit();
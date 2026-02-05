<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

use Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal\PdfLatexBibtexLocalProfile;
use Dagstuhl\Latex\Compiler\LatexCompiler;
use Dagstuhl\Latex\LatexStructures\LatexFile;


header('Content-type: text/plain; charset=UTF-8');

$path = $_GET['path'];
$mode = $_GET['mode'] ?? 'full';
$shellEscape = $_GET['shell-escape'] ?? 0;

$latexFile = new LatexFile($path);
$buildProfile = new PdfLatexBibtexLocalProfile($latexFile);
//$buildProfile->setPdfLatexBin('/Users/m.didas/Docker/docker-latex/docker-pdflatex.sh');
$latexCompiler = new LatexCompiler($latexFile, $buildProfile);

if ($mode === 'version') {
    echo $latexCompiler->getLatexVersion();
    exit();
}

$latexCompiler->compile([ 'mode' => $mode, 'shell-escape' => $shellEscape ]);

echo implode("\n", $latexCompiler->getProfileOutput());

exit();
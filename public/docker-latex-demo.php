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
$profile = new DockerLatexProfile($latexFile, [ 'docker-profile' => 'texlive:2024' ]);

// $path = $profile->archiveSource();

// $profile->unTarArchive();

$profile->compile();

exit();


$latexCompiler = new LatexCompiler($latexFile, $profile);

if ($mode === 'version') {
    echo $latexCompiler->getLatexVersion();
    exit();
}

$latexCompiler->compile([ 'mode' => $mode ]);

echo implode("\n", $latexCompiler->getProfileOutput());

exit();
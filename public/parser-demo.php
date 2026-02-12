<?php

require __DIR__ . '/../vendor/autoload.php';

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Parser\LatexParser;


$latexFile = new LatexFile('../resources/latex-examples/lipics-authors-v2021.1.3/lipics-v2021-sample-article.tex');

$parser = new LatexParser();
$parseTree = $parser->parse($latexFile->getContents());
$abstractEnvs = $parseTree->getEnvironments('abstract');

var_dump($abstractEnvs);

<?php

require __DIR__ . '/../vendor/autoload.php';

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Parser\LatexParser;


$latexFile = new LatexFile('../resources/latex-examples/lipics-authors-v2021.1.3/lipics-v2021-sample-article.tex');

$parser = new LatexParser();
$parseTree = $parser->parse($latexFile->getContents());
$authors = $parseTree->getMacros('author');

var_dump($authors[0]);

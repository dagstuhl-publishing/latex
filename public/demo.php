<?php

require __DIR__ . '/../vendor/autoload.php';

use Dagstuhl\Latex\LatexStructures\LatexFile;

$latexFile = new LatexFile('../resources/latex-examples/lipics-authors-v2021.1.3/lipics-v2021-sample-article.tex');

// read the title

$title = $latexFile->getMacro('title');
var_dump($title->getArgument());

// read the authors

$authors = $latexFile->getMacros('author');
foreach($authors as $author) {
    var_dump($author->getArguments());
}

// read the whole set of metadata as specified in style description src/Styles/StyleDescriptions/LIPIcs_OASIcs_v2021.php

$reader = $latexFile->getMetadataReader();
var_dump($reader->getMetadata());
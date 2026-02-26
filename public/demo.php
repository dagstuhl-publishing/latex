<?php

require __DIR__ . '/../vendor/autoload.php';

use Dagstuhl\Latex\LatexStructures\LatexFile;


\Dagstuhl\Latex\LatexStructures\LatexString::$useParser = true;

$latexFile = new LatexFile('../resources/latex-examples/latex-sample-files/latex-macros-and-environments-test.tex');

$macros = $latexFile->getMacros('section');

foreach($macros as $macro) {
    var_dump($macro->getSnippet(), $macro->_log);
}

$macros = $latexFile->getMacros('dummyMacro');

foreach($macros as $macro) {
    var_dump($macro->getSnippet(), $macro->_log);
}

exit();

$latexFile = new LatexFile('../resources/latex-examples/lipics-authors-v2021.1.3/lipics-v2021-sample-article.tex');

// read the title

$title = $latexFile->getMacro('title');
var_dump($title->getArgument());

// read the authors

$authors = $latexFile->getMacros('author');
foreach($authors as $author) {
    var_dump($author->getArguments());
}

// read bib file

$bibliography = $latexFile->getBibliography();

foreach($bibliography->getBibEntries() as $bibEntry) {
    var_dump($bibEntry->getFields());
}

// read the whole set of metadata as specified in style description src/Styles/StyleDescriptions/LIPIcs_OASIcs_v2021.php

$reader = $latexFile->getMetadataReader();
var_dump($reader->getMetadata());
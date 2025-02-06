# The Dagstuhl LaTeX project

This project is a php class framework developed and used by Dagstuhl Publishing for handling LaTeX submissions on the Dagstuhl Submission Server.
A large part is about parsing metadata from LaTeX files. Since the metadata information comes as the content of style-specific LaTeX macros (e.g. `\title{...}`, `\author{...}`), or environments (e.g. `\begin{abstract}...\end{abstract}`),
we provide... 
- some generic (low-level) methods for parsing macros/environments from the LaTeX source code
- a string-conversion support for LaTeX <-> UTF8

(these two may be useful in a wider context) and
  
- a customizable metadata reader which collects the metadata contained in a LaTeX file and converts it into a structured collection of UTF8-encoded strings.

Dagstuhl Publishing removes all comments from LaTeX files first, so the methods work best with comment-free LaTeX code.

Installation: `composer require dagstuhl/latex`

## 1. Generic methods for parsing LaTeX files

To get to know the basic methods, let's have a look at the LIPIcs example file.
```php
$latexFile = new LatexFile('/resources/latex-examples/lipics-authors-v2021.1.3/lipics-v2021-sample-article.tex');
```
### Parsing macros
The above file contains the macro `\documentclass[a4paper,UKenglish,cleveref, autoref, thm-restate]{lipics-v2021}` 
specifying the underlying style file and the applied options.
 
To read out this information in a structured way, just do the following:
```php
$documentClass = $latexFile->getMacro('documentclass');

$documentClass->getArgument();
// 'lipics-v2021'

$documentClass->getOptions();
// [ 'a4paper', 'UKenglish', 'cleveref', 'autoref', 'thm-restate' ]
```
If a macro may occur several times, apply `$latexFile->getMacros('...')` and you will get an array of `LatexMacro`-objects
```php
$sections = $latexFile->getMacros('section');
$sections[0]->getArgument();
// 'Typesetting instructions -- Summary'
```
If a macro has more than one argument, use the `$macro->getArguments()` method to get its arguments as an array of strings. As an example, take the first author macro from the LIPIcs file:
```php
\author{John Q. Public}{Dummy University Computing Laboratory, [optional: Address], Country \and My second affiliation, Country \and \url{http://www.myhomepage.edu} }{johnqpublic@dummyuni.org}{https://orcid.org/0000-0002-1825-0097}{(Optional) author-specific funding acknowledgements}
```
To split it into the different parts, just do:
```php
$authors = $latexFile->getMacros('author');
$firstAuthor = $authors[0];
$firstAuthor->getArguments();
// [ 'John Q. Public', 'Dummy University ...', 'johnqpublic@dummyuni.org', ... ]
```
### Parsing Environments

To read environments as `LatexEnvironment` objects from a LaTex-file, use  `$latexFile->getEnvironment('...');'` or `$latexFile->getEnvironments('...');'`. 

E.g. `$latexFile->getEnvironment('abstract')` will read the abstract, while `$latexFile->getEnvironments('figure')` will return the array of all figure-environments.

The contents of an environment (i.e. what is between `\begin{...}` and `\end{...}`) can be catched using the `$environment->getContents()` method.

To get the total LaTeX code of the environment (including the `begin/end`) use `$environment->getSnippet()`.

**Note**: The getter-Methods for macros/environments remove `%...`-like comments  from the LaTex file internally. Therefore, the LaTeX snippets they return by the `->getSnippet()` method differ by the comments  

## 2. LaTeX to UTF8 conversion

Converting metadata from LaTeX files to UTF8 can be arbitrarily complicated, depending on the structure of the underlying LaTeX file and the contents of the string to be converted. In most of the cases, `MetadataString::toUtf8String()` should do the job.
```php
$metaString = new MetadataString('Fran\c{c}ois M\"{u}ller-\"{A}hrenbaum recently proved that $a^2 + b^2 = c^2$'.);
$metaString->toUtf8String();
// 'François Müller-Ährenbaum recently proved that a² + b² = c².'
```
(As a second argument the `MetadataString` constructor accepts a LaTex file which will be used to resolve unknown macros.)

The other direction, namely UTF8 to LaTeX, is established by the `Converter` class:
```php
Converter::convert('François Müller-Ährenbaum', Converter::MAP_UTF8_TO_LATEX);
// 'Fran\c{c}ois M\"{u}ller-\"{A}hrenbaum'
```
(Note that math environments cannot be reconstructed from the UTF8 code, so this is essentially limited to plain-text conversion.)

## 3. Metadata-Reader

After a style-description php-class (see `src/Styles/StyleDescriptions`) has been configured for a certain LaTeX `documentclass`, the metadata extraction and conversion is as simple as it can be:
```php
$reader = $latexFile->getMetadataReader();
$metadata = $reader->getMetadata();
// array of metadata - structured and converted as specified in the StyleDescription file
```

## 4. Integration into laravel projects

When used inside a laravel project, the `Storage` class is used for interactions with files.
To interact with your LaTeX installation (e.g., via the `LatexCompiler` class), you must add a config file named `config/latex.php`
provide the following values:
```php
return [
    'paths' => [
        'bin' => env('LATEX_USER_BIN'),   // PATH variable used in default build environment
        'home' => env('LATEX_USER_HOME'), // HOME variable used in default build environment
        'pdf-info-bin' => env('PDF_INFO_BIN'),  // path to your pdf-info binary
        'resources' => env('LATEX_RESOURCES_FOLDER'), // resources folder (in case you want to use your own resources) 
    ],
    'styles' => [
        'registry' => \Your\Local\StylesRegistry::class // in case you want to use a custom styles registry
    ]
];
```
Outside a laravel project, write a global `config` function like so:
```php
function config(): array
{
    return [ 
        'latex.paths.bin' => '/usr/bin:/usr/local/bin',
        'latex.paths.home' => '/path/to/home/of/latex-user',
        ...
    ];
}
```
Note: The config keys related to the LaTeX compiler class have changed in version 2.6. The old keys  `latex.paths.latex-bin`,
`latex.paths.bibtex-bin`, `latex.paths.www-data-path`, `latex.paths.www-data-home` are deprecated (but still supported temporarily 
by the LegacyProfile, which can be applied by instantiating the compiler class as follows
```php
$latexCompiler = new LatexCompiler($latexCompiler, new LegacyProfile());
```

<?php

namespace Dagstuhl\Latex\LatexStructures;

use Dagstuhl\Latex\Bibliography\Bibliography;
use Dagstuhl\Latex\Metadata\MetadataBlock;
use Dagstuhl\Latex\Metadata\MetadataReader;
use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Styles\LatexStyle;
use Dagstuhl\Latex\Utilities\Filesystem;
use Dagstuhl\Latex\Utilities\PlaceholderManager;
use Dagstuhl\Latex\Validation\LatexValidator;

class LatexFile extends LatexString
{
    const EXTENSION_CONDENSED_FILE = '__CONDENSED__.tex';

    private string $path;
    private ?array $labels = NULL;

    public function __construct(string $pathToFile)
    {
        parent::__construct(Filesystem::get($pathToFile));

        $this->path = $pathToFile;
        $this->setLatexFile($this);
    }

    public function reload(): void
    {
        $this->setValue(Filesystem::get($this->getPath()));
    }

    public function saveAs(string $pathToFile, bool $copyPdf = false): void
    {
        Filesystem::put($pathToFile, $this->getContents());

        $pdfPath = $this->getPath('pdf');

        if ($copyPdf AND Filesystem::exists($pdfPath)) {

            $newPdfPath = preg_replace('/\.tex$/', '.pdf', $pathToFile);

            Filesystem::delete($newPdfPath);
            Filesystem::copy($pdfPath, $newPdfPath);
        }
    }

    public function save(): void
    {
        $this->saveAs($this->getPath());
    }

    public function getPath(string $extension = 'tex'): string
    {
        $originalExtension = '.'.pathinfo($this->path, PATHINFO_EXTENSION);
        $path = $this->getDirectory() . $this->getFilename();

        return preg_replace('/'.preg_quote($originalExtension).'$/', '.'.$extension, $path);
    }

    public function getFilename(): string
    {
        return pathinfo($this->path, PATHINFO_BASENAME);
    }

    public function getDirectory(): string
    {
        return pathinfo($this->path, PATHINFO_DIRNAME).'/';
    }

    public function getNumberOfPages(): int
    {
        $pageCount = 0;

        $cmd = config('lzi.latex.paths.pdf-info-bin').' "'.Filesystem::storagePath($this->getPath('pdf')).'"';

        exec ($cmd, $output);

        foreach($output as $op) {
            if (preg_match("/Pages:\s*(\d+)/i", $op, $matches) === 1) {
                $pageCount = intval($matches[1]);
                break;
            }
        }

        return $pageCount;

        /* alternatively: from tex log
        $latexCompiler = new LatexCompiler($this);

        return $latexCompiler->getNumberOfPages();
        */
    }

    public function getContents(bool $withoutComments = false): string
    {
        return $this->getValue($withoutComments);
    }

    public function setContents(string $newContents): void
    {
        $this->setValue($newContents);
    }

    public function getBibliography(): Bibliography
    {
        return new Bibliography($this);
    }

    public function getStyle(): LatexStyle
    {
        $documentClass = $this->getMacro('documentclass');

        $styleName = $documentClass !== NULL
            ? $documentClass->getArgument()
            : 'lipics-v2019';

        return new LatexStyle($styleName);
    }

    public function hasDocumentClass(string $regex): bool
    {
        $contents = $this->getContents(true);

        preg_match('/\\\\documentclass.*\{(' . $regex . ')\}/', $contents, $matches);

        // true if regex match AND the documentclass macro can be parsed uniquely
        if (count($matches) > 0 AND $this->getMacro('documentclass') !== NULL) {
            return true;
        }

        return false;
    }

    public function getValidator(): LatexValidator
    {
        return new LatexValidator($this);
    }

    public function getMetadataReader(): MetadataReader
    {
        return new MetadataReader($this);
    }

    public function getMetadataBlock(): MetadataBlock
    {
        return new MetadataBlock($this);
    }

    /**
     * returns a condensed version of the document in which
     * - all include-files are imported (if found on storage)
     * - all comments (%...) are removed
     * - the \(re)newcommands are in standard form (i.e. \(re)newcommand{...}{...})
     *
     * suitable for further validation and reading specific macros or environments
     */
    public function getCondensedFile(bool $fromCache = false): static
    {
        $path = $this->getPath(self::EXTENSION_CONDENSED_FILE);

        if (Filesystem::exists($path) AND $fromCache) {
            return new static($path);
        }

        $log = 'Generating simplified file for '.$this->getPath()."\n";

        $nr = 0;

        while (count($this->getSubFiles(true)) > 0 AND $nr < 4) {
            $log .= $this->importSubFilesForCondensedFile()."\n";
            $nr++;
        }

        $this->removeComments();
        $log .= '* removed comments, file-size afterwards: '.strlen($this->getContents())."\n";

        $this->normalizeNewCommands();
        $log .= '* normalized new commands, file-size afterwards: '.strlen($this->getContents())."\n";

        $countEndDocument = 0;
        $this->deleteEverythingAfterEndDocument($countEndDocument);
        $log .= '* removed everything after \end{document}, file-size afterwards: '.strlen($this->getContents())."\n";

        if ($countEndDocument > 1) {
            $log .= '* NOTE: <code>\end{document}</code> found '.$countEndDocument.' times; perhaps I cut off too much form the LaTeX source';
        }

        $this->saveAs($path, true); // count pages only works if pdf is present

        $logPath = str_replace('.tex', '.log', $path);

        Filesystem::put($logPath, $log);

        return new static($path);
    }

    public function removeCondensedFile(): void
    {
        $files = Filesystem::files($this->getDirectory());

        foreach($files as $file) {
            if (str_contains($file, self::EXTENSION_CONDENSED_FILE)) {
                Filesystem::delete($file);
            }
        }
    }

    public function deleteEverythingAfterEndDocument(?int &$countEndDocument = 0): void
    {
        $contents = $this->getContents();

        $placeholderMgr = new PlaceholderManager();

        $contents = $placeholderMgr->substitutePatterns( LatexPatterns::SIMPLE_LATEX_COMMENTS, $contents);

        $countEndDocument = substr_count($contents, '\end{document}');

        // find first \end{document}
        $pos = strpos($contents, '\end{document}');

        if ($pos !== false) {
            $contents = substr($contents, 0, $pos + strlen('\end{document}'));
        }

        $contents = $placeholderMgr->reSubstitute($contents);

        $this->setContents($contents);
    }

    /**
     * Replace different syntax variants for declaration of new commands with: \newcommand{...}{...}
     */
    public function normalizeNewCommands(): void
    {
        $contents = $this->getContents();

        // move \newcommand to the beginning of a line
        $contents = preg_replace('/([^\n])(\\\\newcommand|\\\\renewcommand)/', '$1'."\n".'$2', $contents);

        // remove blanks between arguments
        $contents = preg_replace(
            '/(\\\\newcommand\{.{1,20}\})(|\[.{1,20}\])(|\[.{1,20}\])(\n|\s+)(\{)/smU',
            '$1$2$3$5', $contents);

        // add curly braces if missing for first argument
        $contents = preg_replace('/(\\\\newcommand)(\\\\.+)( \{|\{|\[)/U', '$1{$2}$3', $contents);

        // add curly braces for both arguments if necessary
        $contents = preg_replace(
            '/(\\\\newcommand)(\\\\[^\{]+)(\\\\[^\{]+)(\n)/U',
            '$1{$2}{$3}$4',
            $contents);

        $this->setContents($contents);
    }

    /**
     * @return LatexSubFile[]
     */
    public function getSubFiles(bool $onlyExistingFiles = false): array
    {
        return LatexSubFile::getAllSubFilesOf($this, $onlyExistingFiles);
    }

    /**
     * @return string[]|null
     */
    private function getInputDescription(string $name, string $pattern, bool $onlyExistingFiles): ?array
    {
        $contents = $this->getContents();

        $filename = $this->getDirectory().$name;
        $filename = trim($filename);

        $count = substr_count($contents, $pattern);

        $import = true;
        $fileExists = true;

        if (Filesystem::exists($filename.'.tex')) {
            $filename .= '.tex';
        }
        elseif (!Filesystem::exists($filename)) {
            $fileExists = false;
            $import = false;
        }

        if (StringHelper::endsWith($filename, '.pgf')) {
            $import = false;
        }

        if (!$onlyExistingFiles OR ($onlyExistingFiles AND $fileExists)) {
            return [
                'name' => $name,
                'pattern' => $pattern,
                'filename' => $filename,
                'fileExists' => $fileExists,
                'count' => $count,
                'import' => $import
            ];
        }

        return NULL;
    }

    private function importSubFilesForCondensedFile(): string
    {
        $contents = $this->getContents();

        $subFiles = $this->getSubFiles(true);

        $fileSize = strlen($contents);

        $log = '* starting new input pass: '.count($subFiles).' inputs left'."\n";

        foreach($subFiles as $subFile) {

            if ($subFile->shouldBeImported()  AND $subFile->getType() !== LatexSubFile::TYPE_PACKAGE) {

                try {
                    $fileContents = ' ' . $subFile->getContents();
                }
                catch(\Exception $ex) {
                    $fileContents = ' ';
                }

                $snippet = $subFile->getSnippet();
                if (StringHelper::startsWith($snippet, '\input{')
                    OR (StringHelper::startsWith($snippet, '\input ') AND $subFile->getReferenceCount() === 1)) {
                    $contents = str_replace($snippet, $fileContents, $contents);
                }
                else {
                    $contents = str_replace([
                        $snippet.' ',
                        $snippet.'\\',
                        $snippet."\n",
                        $snippet."\r",
                    ], $fileContents, $contents);
                }

                $log .= '    + importing '.$subFile->getPath();
                $log .= ', file size after import: '. strlen($contents)."\n";

                if (strlen($contents) <= $fileSize) {
                    $log .= '    + WARNING: Size did not increase; snippet: '.$subFile->getSnippet().'; reference count: '.$subFile->getReferenceCount()."\n";
                }
            }
        }

        $this->setContents($contents);

        $this->removeComments();

        $log .= '    + file size after removing comments: '.strlen($this->getContents())."\n";
        $log .= '    + memory usage: '.memory_get_usage(true)."\n";

        return $log;
    }

    /**
     * array of arrays with keys 'name' and 'snippet'
     */
    public function getIfs(): array
    {
        $contents = $this->getContents(true);

        $ifs = [];

        // collect declarations of the form \newif\if[name] and collect the [name]s
        $snippets = StringHelper::extract('/\\\\newif.*\\\\if/U', $contents, 40, false);

        $names = [];

        foreach($snippets as $if) {
            preg_match('/(\\\\if.*)[\n\r\s\\\\]/U', $if, $match);

            if (isset($match[1]) AND !in_array($match[1], [ '\ifGPcolor', '\ifGPblacktext' ])) {
                $names[] = $match[1];
            }
        }

        // look for usage of these if-constructions
        foreach($names as $name) {
            $regex = '/'.preg_quote($name).'/';

            $snippets = StringHelper::extract($regex, $contents, 40, false);

            foreach($snippets as $snippet) {
                $ifs[] = [
                    'name' => $name,
                    'snippet' => $snippet
                ];
            }
        }

        // look for \ifnum-constructions
        $snippets = StringHelper::extract('/\\\\ifnum(.*)[\=\>\<]/', $contents, 40, false);

        foreach($snippets as $ifnum) {
            preg_match('/\\\\ifnum(.*)[\=\>\<]/U', $ifnum, $match);

            $ifs[] = [
                'name' => '\ifnum'.$match[1] ?? 'unknown',
                'snippet' => $ifnum
            ];
        }

        return $ifs;
    }


    /**
     * returns [author, year] version of the cite-macro containing the specifies identifier
     */
    public function getCitation(string $identifier): string
    {
        $identifier = trim($identifier);

        if (!empty($identifier)) {
            if (str_contains($identifier, ',')) {

                $identifiers = explode(',', $identifier);

                $citeString = '';

                foreach ($identifiers as $identifier) {
                    $identifier = trim($identifier);

                    $citeString .= $this->getCitation($identifier);
                }

                return str_replace('][', '; ', $citeString);
            }

            $bibContents = $this->getBibliography()->getBibContents(true);

            $lines = explode("\n", $bibContents);

            $author = '';
            $year = '';

            $found = false;

            foreach ($lines as $key => $line) {
                $line = trim($line);
                $lines[$key] = $line;

                if (StringHelper::startsWith($line, '@') and str_contains($line, $identifier)) {
                    $found = true;
                }

                if ($found) {
                    $line = preg_replace('/\h+\=\h+\{/', ' = {', $line);

                    if (StringHelper::startsWith($line, 'author = {')) {
                        $author = $line;

                        $author = str_replace('author = {', '', $author);
                        $author = preg_replace('/,$/', '', $author);
                        $author = preg_replace('/\}$/', '', $author);
                    }

                    if (StringHelper::startsWith($line, 'year')) {
                        $match = [];
                        preg_match('/([0-9]{4,4})/', $line, $match);

                        if (isset($match[1])) {
                            $year = $match[1];
                        }
                    }
                }

                if ($found and StringHelper::startsWith($line, '}')) {
                    break;
                }
            }

            $authors = explode(' and ', $author);

            foreach ($authors as $nr => $author) {
                $author = explode(',', $author);
                $authors[$nr] = trim($author[0]);
            }

            if (count($authors) > 2) {
                $author = $authors[0] . ' et al.';
            } else {
                $author = implode(' and ', $authors);
            }

            if ($author === '' or $year === '') {
                return '\cite{' . $identifier . '}';
            }

            return '[' . $author . ', ' . $year . ']';
        } else {
            return '[UNKNOWN, ???]';
        }
    }

    public function getCiteT(string $identifier): string
    {
        $auxFile = new LatexFile($this->getPath('aux'));

        $bibciteMacros = $auxFile->getMacros('bibcite');

        $identifiers = explode(',', $identifier);

        $result = '';

        foreach($identifiers as $idNo=>$identifier) {
            $identifier = trim($identifier);

            foreach($bibciteMacros as $macro) {
                if ($macro->getArguments()[0] === $identifier) {
                    $arg = $macro->getArguments()[1];

                    $latexString = new LatexString('\bibcite'.$arg);

                    $components = $latexString->getMacro('bibcite')->getArguments();

                    if ($idNo > 0) {
                        $result .= ', ';
                    }

                    $result .= preg_replace('/^\{(.*)\}$/', '$1', $components[2])
                        .' \cite{'.$identifier.'}'; // this is the year, if needed: %'.$components[1];
                }
            }
        }

        return $result;
    }

    /**
     * @return LatexLabel[]
     */
    public function getLabels(bool $cache = true): array
    {
        if ($this->labels !== NULL AND $cache) {
            return $this->labels;
        }

        $auxPath = $this->getPath('aux');

        if (!Filesystem::exists($auxPath)) {
            return [];
        }

        $auxFile = new static($auxPath);

        $rawLabels = $auxFile->getMacros('newlabel');

        $labels = [];

        foreach($rawLabels as $label) {
            $args = $label->getArguments();

            if (count($args) < 2) {
                continue;
            }

            $labels[$args[0]] = new LatexLabel($args[0], $args[1]);
        }

        $this->labels = $labels;

        return $labels;
    }

    public function getLabelReference(string $identifier, string $refCommand = 'ref', bool $markWithBackslash = false): string
    {
        $labels = $this->getLabels();

        if (!isset($labels[$identifier])) {
            return '\\UNKNOWN_LABEL';
        }

        $ref = '\\COULD_NOT_RESOLVE_LABEL';

        if ($refCommand === 'ref' OR $refCommand === 'cref') {
            $ref = $labels[$identifier]->getReferenceText();
        }
        elseif ($refCommand === 'pageref') {
            $ref = $labels[$identifier]->getPage();
        }

        if ($markWithBackslash) {
            $ref = '\\'.$ref;
        }

        return $ref;
    }


    public function getDetailedPageNumbers(): array
    {
        $auxPath = $this->getPath('aux');

        $output = [];
        $output['pageNumberEndAbstract'] = -1;
        $output['pageNumberStartBibliography'] = -1;
        $output['pageNumberEndBibliography'] = -1;
        $output['pageNumberStartAppendix'] = -1;
        $output['lastpage'] = -1;

        if (!Filesystem::exists($auxPath)) {
            return $output;
        }

        $auxFile = new LatexFile($auxPath);

        //\gdef\@pageNumberEndAbstract{1}
        $s = $auxFile->getMacros('gdef\@pageNumberEndAbstract');
        if(count($s) == 1 AND count($s[0]->getArguments()) == 1){
            $output['pageNumberEndAbstract'] = $s[0]->getArguments()[0];
        }

        //\gdef\@pageNumberStartBibliography{11}
        $s = $auxFile->getMacros('gdef\@pageNumberStartBibliography');
        if(count($s) == 1 AND count($s[0]->getArguments()) == 1){
            $output['pageNumberStartBibliography'] = $s[0]->getArguments()[0];
        }

        //\gdef\@pageNumberEndBibliography{11}
        $s = $auxFile->getMacros('gdef\@pageNumberEndBibliography');
        if(count($s) == 1 AND count($s[0]->getArguments()) == 1){
            $output['pageNumberEndBibliography'] = $s[0]->getArguments()[0];
        }

        //\gdef\@pageNumberStartAppendix{11}
        $s = $auxFile->getMacros('gdef\@pageNumberStartAppendix');
        if(count($s) == 1 AND count($s[0]->getArguments()) == 1){
            $output['pageNumberStartAppendix'] = $s[0]->getArguments()[0];
        }

        //\xdef\lastpage@lastpage{12}
        $s = $auxFile->getMacros('xdef\lastpage@lastpage');
        if(count($s) == 1 AND count($s[0]->getArguments()) == 1){
            $output['lastpage'] = $s[0]->getArguments()[0];
        }

        //\gdef \@abspage@last{11}
        $s = $auxFile->getMacros('gdef \@abspage@last');
        if(count($s) == 1 AND count($s[0]->getArguments()) == 1){
            $output['lastpage'] = $s[0]->getArguments()[0];
        }

        return $output;
    }
}
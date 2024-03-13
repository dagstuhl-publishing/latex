<?php

namespace Dagstuhl\Latex\Bibliography;

use Exception;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\LatexStructures\LatexString;
use Dagstuhl\Latex\Strings\MetadataString;
use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Utilities\Filesystem;

class Bibliography
{
    const TYPE_MIXED = 'mixed-bib';
    const TYPE_BIBTEX = 'bibtex-bib';
    const TYPE_BIBLATEX = 'biblatex-bib';
    const TYPE_INLINE = 'inline-bib';
    const TYPE_NONE = 'no-bib';
    const BIB_STYLE_NONE = 'no-bib-style';
    const BIB_STYLE_INVALID = 'invalid-bib-style';
    const BIB_STYLE_PLAIN_URL = 'plainurl';
    const BIB_STYLE_PLAIN = 'plain';

    private LatexFile $latexFile;
    private BBlFile $bblFile;

    /** @var BibFile[] */
    private array $bibFiles;
    private ?string $bibContents = NULL;
    private ?string $bblContents = NULL;

    /** @var BibEntry[]|null */
    private ?array $bibEntries = NULL;

    public function __construct(LatexFile $latexFile)
    {
        $this->latexFile = $latexFile;
        $this->bblFile = new BblFile($latexFile->getPath('bbl'));
        $this->bibFiles = [];
    }

    public function getType(): string
    {
        $contents = $this->latexFile->getContents(true);

        $inlineBib = str_contains($contents, '\begin{thebibliography}');
        $hasBibMacros = count($this->latexFile->getMacros('bibliography')) > 0;
        $hasBiblatexBib = str_contains($contents, '\printbibliography');

        if (
            ($inlineBib AND $hasBibMacros)
            OR ($inlineBib AND $hasBiblatexBib)
            OR ($hasBibMacros AND $hasBiblatexBib)
        ) {
            return self::TYPE_MIXED;
        }
        elseif ($hasBiblatexBib) {
            return self::TYPE_BIBLATEX;
        }
        elseif ($inlineBib) {
            return self::TYPE_INLINE;
        }
        elseif ($hasBibMacros) {
            return self::TYPE_BIBTEX;
        }

        return self::TYPE_NONE;
    }

    public function getBblFile(): BblFile
    {
        return $this->bblFile;
    }

    /**
     * @return BibFile[]
     */
    public function getBibFiles(): array
    {
        if (count($this->bibFiles) === 0) {
            foreach ($this->getUsedBibFileNames() as $name) {
                $path = $this->latexFile->getDirectory() . $name;

                if (!str_ends_with($path,'.bib')) {
                    $path .= '.bib';
                }

                $this->bibFiles[] = new BibFile($path, $this);
            }
        }

        return $this->bibFiles;
    }

    /**
     * @return string[]
     */
    private function getUsedBibFileNames(): array
    {
        $bibFiles = [];

        if ($this->getType() === self::TYPE_BIBLATEX) {
            $bibResources = $this->latexFile->getMacros('addbibresource');

            foreach ($bibResources as $bib) {
                $bibFiles[] = $bib->getArgument();
            }
        }

        $bibMacros = $this->latexFile->getMacros('bibliography');

        foreach ($bibMacros as $bib) {
            $files = explode(',', $bib->getArgument());
            $bibFiles = array_merge($bibFiles, $files);
        }

        foreach ($bibFiles as $key => $file) {
            $bibFiles[$key] = trim($file);
        }

        return $bibFiles;
    }

    public function getFirstBibFileName(bool $addFileExtension = false): ?string
    {
        $usedBibFiles = $this->getUsedBibFileNames();

        if (count($usedBibFiles) === 0) {
            return NULL;
        }

        $file = trim($usedBibFiles[0]);

        if ($addFileExtension AND !StringHelper::endsWith($file,'.bib')) {
            $file .= '.bib';
        }

        return $file;
    }

    public function getFirstBibFile(): ?BibFile
    {
        $this->getBibFiles();
        return $this->bibFiles[0] ?? NULL;
    }

    /**
     * @return string[]
     *
     * returns paths of all bib-files in the same directory
     */
    public function getPathsToAllBibFiles(): array
    {
        $files = Filesystem::files($this->latexFile->getDirectory());

        $bibFiles = [];

        foreach($files as $file) {
            if (StringHelper::endsWith($file, '.bib')) {
                $bibFiles[] = $file;
            }
        }

        return $bibFiles;
    }

    /**
     * @return string[]
     *
     * returns paths of all bib-files used in \bibliography macros
     */
    public function getPathsToUsedBibFiles(): array
    {
        $paths = [];

        foreach($this->getUsedBibFileNames() as $fileName) {

            if ($fileName === '\jobname') {
                $fileName = $this->latexFile->getFilename();
                $fileName = str_replace(LatexFile::EXTENSION_CONDENSED_FILE, 'bib', $fileName);
            }

            if (!StringHelper::endsWith($fileName, '.bib')) {
                $fileName .= '.bib';
            }

            $paths[] = $this->latexFile->getDirectory().$fileName;
        }

        return $paths;
    }

    public function getBibContents(bool $useCache = true): string
    {
        if ($useCache AND $this->bibContents !== NULL) {
            return $this->bibContents;
        }

        $contents = '';

        foreach ($this->getPathsToUsedBibFiles() as $path) {
            try {
                $contents .= "\n\n" . Filesystem::get($path);
            }
            catch(Exception $ex) { }
        }

        $this->bibContents = $contents;

        return $contents;
    }

    public function getBblContents(bool $useCache = true): string
    {
        if ($useCache AND $this->bblContents !== NULL) {
            return $this->bblContents;
        }

        $contents = '';

        $path = $this->latexFile->getPath('bbl');

        try {
            $contents = Filesystem::get($path) ?? '';
        }
        catch(Exception $ex) { }

        $this->bblContents = $contents;

        return $contents;
    }


    /**
     * @return string[][]
     */
    public function getReferences(): array
    {
        $bibStyle = $this->getBibStyle();

        $references = [];

        switch ($bibStyle) {

            case self::BIB_STYLE_PLAIN_URL:
            case self::BIB_STYLE_PLAIN:
            case self::BIB_STYLE_NONE:
            case self::BIB_STYLE_INVALID:

                foreach($this->getReferencesPlainUrl() as $internalReference) {
                    unset($internalReference['originalEntry']);
                    $references[] = $internalReference;
                }

                break;
        }

        return $references;
    }

    public function getBibStyle(): string
    {
        $macros = $this->latexFile->getMacros('bibliographystyle');

        return match(count($macros)) {
            1 => $macros[0]->getArgument(),
            0 => self::BIB_STYLE_NONE,
            default => self::BIB_STYLE_INVALID
        };
    }

    /**
     * @return string[][]
     */
    private function getReferencesPlainUrl(): array
    {
        $bibKeyArray = [];

        $bblString = $this->getBblContents();

        $bblString = str_replace('\begin{thebibliography}{10}', '', $bblString);
        $bblString = str_replace('\end{thebibliography}', '', $bblString);

        $entries = explode('\bibitem', $bblString);

        unset($entries[0]);

        foreach ($entries as $key => $entry) {

            $entry = '\bibitem' . $entry;
            $originalEntry = $entry;

            $latexString = new LatexString($entry);

            foreach ($latexString->getMacros('bibitem') as $macro) {

                $bibKey = $macro->getArgument();
                $entry = str_replace($macro->getSnippet(), '', $entry);
            }

            $entry = str_replace("\n", '', $entry);
            $entry = str_replace("\r", '', $entry);

            $entry = str_replace('\\ ', ' ', $entry);
            $entry = str_replace('\ ', ' ', $entry);
            $entry = str_replace('\newblock', '', $entry);
            $entry = str_replace(' {\path{', '{\path{', $entry);
            $entry = str_replace('\href ', '\href', $entry);
            $entry = str_replace('\relax ', '', $entry);
            $entry = str_replace('\small', '', $entry);
            $entry = str_replace('\allowbreak', '', $entry);

            // sort out \arxiv{...} macros and transform them to \url{https://arxiv.org/abs/...}
            $latexString = new LatexString($entry);

            foreach ($latexString->getMacros('arxiv') as $macro) {
                $arg = $macro->getArgument();
                $entry = str_replace($macro->getSnippet(), '\url{https://arxiv.org/abs/'.$arg.'}', $entry);
                //$entry = str_replace($macro->getSnippet(), '\href{https://arxiv.org/abs/'.$arg.'}{\path{arXiv:'.$arg.'}}', $entry);
            }

            $metaString = new MetadataString($entry, $this->latexFile);

            $metaString->expandMacros()->expandMacros()->normalizeMacro(true);

            $cleanEntry = $metaString->getString();

            $latexString = new LatexString($cleanEntry);

            foreach ($latexString->getMacros('path') as $macro) {

                $cleanEntry = str_replace($macro->getSnippet() . '.', '', $cleanEntry);
                $cleanEntry = str_replace($macro->getSnippet(), '', $cleanEntry);
            }

            foreach ($latexString->getMacros('noopsort') as $macro) {

                $cleanEntry = str_replace($macro->getSnippet(), '', $cleanEntry);
            }

            foreach ($latexString->getMacros('uppercase') as $macro) {

                $cleanEntry = str_replace($macro->getSnippet(), $macro->getArgument(), $cleanEntry);
            }

            foreach ($latexString->getMacros('acro') as $macro) {

                $cleanEntry = str_replace($macro->getSnippet(), $macro->getArgument(), $cleanEntry);
            }

            foreach ($latexString->getMacros('dutchPrefix') as $macro) {

                $cleanEntry = str_replace($macro->getSnippet(), '', $cleanEntry);
            }

            foreach ($latexString->getMacros('abbrev') as $macro) {

                $abbrevArgs = $macro->getArguments();
                $abbrevContent = $abbrevArgs[0];
                $cleanEntry = str_replace($macro->getSnippet(), $abbrevContent, $cleanEntry);
            }

            $cleanEntry = str_replace('\em ', ' ', $cleanEntry);
            $cleanEntry = str_replace('\em\/,', ',', $cleanEntry);
            $cleanEntry = str_replace('\em\/.', '.', $cleanEntry);

            // getting URL information; in case the url is linked to doi or arXiv,
            // the cleanEntry has to be manually extended by the url
            $url = NULL;
            $urlType = NULL;

            $latexString = new LatexString($cleanEntry);

            foreach ($latexString->getMacros('htmladdnormallink') as $macro) {
                $linkArgs = $macro->getArguments();
                $linkText = $linkArgs[0];
                $url = $linkArgs[1];

                $cleanEntry = str_replace($macro->getSnippet(), $linkText . '. ', $cleanEntry);
                $urlType = 'url';
            }

            foreach ($latexString->getMacros('myurl') as $macro) {
                $url = $macro->getArgument();
                $cleanEntry = str_replace($macro->getSnippet(), '', $cleanEntry);
                $urlType = 'url';
            }

            foreach ($latexString->getMacros('burl') as $macro) {
                $url = $macro->getArgument();
                $cleanEntry = str_replace($macro->getSnippet(), '', $cleanEntry);
                $urlType = 'url';
            }


            // extracting 'url' and 'href' from original entry before normalizing
            $latexString = new LatexString($entry);

            foreach ($latexString->getMacros('url') as $macro) {
                $url = $macro->getSnippet();
                $metaDataString = new MetadataString($url);
                $metaDataString->normalizeMacro();
                $url = $metaDataString->getString();
                $urlType = 'url';
            }


            foreach ($latexString->getMacros('href') as $macro) {
                $url = $macro->getSnippet();
                $metaDataString = new MetadataString($url);
                $metaDataString->normalizeMacro();
                $url = $metaDataString->getString();
                $urlType = 'url';
            }

            if ($url !== NULL) {

                if (StringHelper::endsWith($cleanEntry,$url.'.')) {
                    $cleanEntry = str_replace($url.'.','', $cleanEntry);
                }

                if (str_contains($url, 'doi')) {
                    $urlType = 'doi';
                } elseif (str_contains($url, 'arxiv') OR str_contains($url, 'arXiv')) {
                    $urlType = 'arXiv';
                }

                if (StringHelper::endsWith($cleanEntry, 'URL: ')) {

                    // if cleanEntry already contains URL in plain text, then we do not append the URL at the end again
                    if (str_contains($cleanEntry, $url)) {
                        $cleanEntry = StringHelper::replace($cleanEntry, 'URL: ', '');
                    } else {
                        $cleanEntry .=  $url . '.';
                    }
                } elseif (StringHelper::endsWith($cleanEntry, 'URL:')) {

                    // if cleanEntry already contains URL in plain text, then we do not append the URL at the end again
                    if (str_contains($cleanEntry, $url)) {
                        $cleanEntry = StringHelper::replace($cleanEntry, 'URL:', '');
                    } else {
                        $cleanEntry .= ' ' . $url . '.';
                    }
                }
                else {

                    // if cleanEntry already contains URL in plain text, then we do not append the URL at the end again
                    if (!strpos($cleanEntry, $url)) {
                        $cleanEntry .= ' URL: ' . $url . '.';
                    }
                }

                $url = str_replace('\&', '&', $url);
            }

            $cleanEntry = str_replace('na{\" \i}ve', 'naïve', $cleanEntry);
            $cleanEntry = str_replace('Vel’skĭ{\i{}}', 'Vel’skĭı', $cleanEntry);
            $cleanEntry = str_replace('\&', '&', $cleanEntry);

            $cleanEntry = str_replace('\nobreakdash', '', $cleanEntry);
            $cleanEntry = str_replace('\footnotesize', '', $cleanEntry);
            $cleanEntry = str_replace('\small', '', $cleanEntry);
            $cleanEntry = str_replace('\normalfont', '', $cleanEntry);

            $cleanEntry = preg_replace('/\\\\\^([0-9]{1,1})/', '^$1', $cleanEntry); // transforms "\^number" to "^number"
            $cleanEntry = str_replace('\em', '', $cleanEntry);

            $cleanEntry = trim($cleanEntry);
            $cleanEntry = StringHelper::replaceMultipleWhitespacesByOneBlank($cleanEntry);

            /*
            if (!mb_check_encoding($cleanEntry, 'UTF-8')) {
                echo "CAUTION: String '$cleanEntry' is not UTF-8";
            */
            if (!mb_check_encoding($cleanEntry, 'UTF-8')){
                $cleanEntry = '\\NON-UTF-8 CHAR CONTAINED! Check!';
            }

            $bibKeyArray[$bibKey] = [
                'originalEntry' => $originalEntry,
                'order' => $key,
                'text' => $cleanEntry,
                'url' => $url,
                'urlType' => $urlType
            ];
        }

        // now check for cross-references and replace them by the corresponding bibKey
        return $this->getRefsWithCorrectCrossRefs($bibKeyArray);
    }

    /**
     * @param string[][] $bibKeyArray
     * @return string[][]
     */
    private function getRefsWithCorrectCrossRefs(array $bibKeyArray): array
    {
        foreach($bibKeyArray as $bibKey => $entry) {

            $text = $entry['text'];
            $latexString = new LatexString($text);

            foreach ($latexString->getMacros('cite') as $macro) {

                $citeArgument = $macro->getArgument();

                if (!str_contains($citeArgument, ',')) {

                    try {
                        $refNumber = $bibKeyArray[$citeArgument]['order'];
                    } catch(Exception $ex) {
                        echo $ex->getMessage();
                        echo '<br>' . $citeArgument;
                    }

                } else {

                    $cites = explode(',',$citeArgument);

                    foreach ($cites as $cite) {

                        $refArray[] = $bibKeyArray[$cite]['order'];

                    }

                    $refNumber = implode(',', $refArray);

                }

                if (isset($refNumber)) {
                    $correctCitedText = str_replace($macro->getSnippet(), '['. $refNumber . ']', $text);
                } else {
                    $correctCitedText = str_replace($macro->getSnippet(), '[ ??? ]', $text);
                }

                $bibKeyArray[$bibKey]['text'] = $correctCitedText;
            }


            $correctCitedText = str_replace('. {','. ', $bibKeyArray[$bibKey]['text']);
            $correctCitedText = str_replace('}.','.', $correctCitedText);
            $correctCitedText = str_replace('},',',', $correctCitedText);
            $correctCitedText = str_replace('}:',':', $correctCitedText);

            $correctCitedText = str_replace(' {',' ', $correctCitedText);
            $correctCitedText = str_replace('} ',' ', $correctCitedText);

            //$correctCitedText = str_replace('}','', $correctCitedText);
            //$correctCitedText = str_replace('}','', $correctCitedText);

            if (!str_contains($correctCitedText, '\{')) {
                $correctCitedText = str_replace('{', '', $correctCitedText);
            }

            if (!str_contains($correctCitedText, '\}')) {
                $correctCitedText = str_replace('}', '', $correctCitedText);
            }

            $bibKeyArray[$bibKey]['text'] = $correctCitedText;

        }

        return $bibKeyArray;
    }

    public function getBibEntries(bool $useCache = true): array
    {
        if ($useCache AND $this->bibEntries !== NULL) {
            return $this->bibEntries;
        }

        $this->bibEntries = [];

        foreach($this->getBibFiles() as $bibFile) {
            var_dump($bibFile->getPath());
            $this->bibEntries = array_merge($this->bibEntries, $bibFile->getAllBibEntries());
        }

        return $this->bibEntries;
    }
}

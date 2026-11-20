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

    const REFERENCE_SOURCE_BBL = 'bbl';
    const REFERENCE_SOURCE_PDF = 'pdf';


    private LatexFile $latexFile;
    private string $bblPath;
    private BBlFile $bblFile;

    /** @var BibFile[] */
    private array $bibFiles;
    private ?string $bibContents = NULL;
    private ?string $bblContents = NULL;

    /** @var BibEntry[]|null */
    private ?array $bibEntries = NULL;

    public function __construct(LatexFile $latexFile, ?string $bblPath = null)
    {
        $this->latexFile = $latexFile;
        $this->bblPath = $bblPath ?? $latexFile->getPath('bbl');
        $this->bblFile = new BblFile($this->bblPath, $latexFile);
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

        $path = $this->bblPath;

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
    public function getReferences(string $referenceSource = self::REFERENCE_SOURCE_BBL): array
    {
        $bibStyle = $this->getBibStyle();

        $references = [];

        switch ($bibStyle) {
            case self::BIB_STYLE_PLAIN_URL:
            case self::BIB_STYLE_PLAIN:
            case self::BIB_STYLE_NONE:
            case self::BIB_STYLE_INVALID:

                if ($referenceSource === self::REFERENCE_SOURCE_BBL) {
                    $references = $this->bblFile->getReferences(false);
                }
                elseif ($referenceSource !== self::REFERENCE_SOURCE_PDF) {
                    // TODO:
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

    public function getBibEntries(bool $useCache = true): array
    {
        if ($useCache AND $this->bibEntries !== NULL) {
            return $this->bibEntries;
        }

        $this->bibEntries = [];

        foreach($this->getBibFiles() as $bibFile) {
            $this->bibEntries = array_merge($this->bibEntries, $bibFile->getAllBibEntries());
        }

        return $this->bibEntries;
    }
}

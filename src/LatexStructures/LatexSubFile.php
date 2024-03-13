<?php

namespace Dagstuhl\Latex\LatexStructures;

use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Utilities\Filesystem;
use Dagstuhl\Latex\Utilities\PlaceholderManager;

class LatexSubFile
{
    const TYPE_UNKNOWN = 'unknown';
    const TYPE_INPUT = 'input';
    const TYPE_INCLUDE = 'include';
    const TYPE_IMPORT = 'import';
    const TYPE_SUBFILE = 'subfile';
    const TYPE_PACKAGE = 'package';

    const SUB_FILE_PATTERNS = [
        [ 'regex' => '=\\\\input\{(.*)\}=U',              'snippet' => '\input{@@@}',     'type' => self::TYPE_INPUT ],
        [ 'regex' => '=\\\\input (.*)[ \\\\\n\r]{1}=',    'snippet' => '\input @@@',      'type' => self::TYPE_INPUT ],
        [ 'regex' => '=\\\\include\{(.*)\}=U',            'snippet' => '\include{@@@}',   'type' => self::TYPE_INCLUDE ],
        [ 'regex' => '=\\\\include (.*)[ \\\\\n\r]{1}=',  'snippet' => '\include @@@',    'type' => self::TYPE_INCLUDE ],
        [ 'regex' => '=\\\\import\{(.*)\}\{(.*)\}=U',     'snippet' => '\import{@@@}{@@}', 'type' => self::TYPE_IMPORT ],
        [ 'regex' => '=\\\\subimport\{(.*)\}\{(.*)\}=U',  'snippet' => '\subimport{@@@}{@@}', 'type' => self::TYPE_IMPORT ],
        [ 'regex' => '=\\\\subfile\{(.*)\}=U',            'snippet' => '\subfile{@@@}',   'type' => self::TYPE_SUBFILE ],
        [ 'regex' => '=\\\\usepackage\{(.*)\}=U',         'snippet' => '\usepackage{@@@}', 'type' => self::TYPE_PACKAGE ]
    ];

    protected string $name;
    protected string $snippet;
    protected bool $fileExists;
    protected int $referenceCount;
    protected bool $import;
    protected string $path;
    protected string $contents;
    protected LatexFile $mainLatexFile;

    public function __construct(string $name, string $snippet, LatexFile $mainLatexFile)
    {
        $this->name = $name;

        $this->import = true;
        $this->fileExists = true;

        $this->mainLatexFile = $mainLatexFile;

        $path = $this->mainLatexFile->getDirectory().$name;
        $path = trim($path);

        if (Filesystem::exists($path.'.tex') AND !StringHelper::startsWith($snippet, '\usepackage{')) {
            $path .= '.tex';
        }
        elseif (Filesystem::exists($path.'.sty')) {
            $path .= '.sty';
        }
        elseif (!Filesystem::exists($path)) {
            $this->fileExists = false;
            $this->import = false;
        }

        if (StringHelper::endsWith($path, '.pgf')) {
            $this->import = false;
        }

        $this->path = $path;
        $this->snippet = $snippet;
        $this->referenceCount = substr_count($this->mainLatexFile->getContents(), $this->snippet);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function fileExists(): bool
    {
        return $this->fileExists;
    }

    public function shouldBeImported(): bool
    {
        return $this->import;
    }

    public function getType(): string
    {
        if (StringHelper::endsWith($this->getPath(), '.sty')) {
            return self::TYPE_PACKAGE;
        }

        $string = substr($this->snippet, 1);

        foreach([ self::TYPE_INPUT, self::TYPE_INCLUDE, self::TYPE_SUBFILE ] as $type) {
            if (StringHelper::startsWith($string, $type)) {
                return $type;
            }
        }

        return self::TYPE_UNKNOWN;
    }

    public function getReferenceCount(): int
    {
        return $this->referenceCount;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getContents(bool $unmodified = false): string
    {
        $contents = $this->fileExists
            ? Filesystem::get($this->path)
            : '';

        if ($unmodified) {
            return $contents;
        }

        $latexString = new LatexString($contents);

        // files included with \subfile{...} can contain documentclass macro
        foreach($latexString->getMacros('documentclass') as $docClass) {
            $contents = str_replace($docClass->getSnippet(), '', $contents);
        }

        // .sty files may contain a \ProvidesPackage command
        foreach($latexString->getMacros('ProvidesPackage') as $docClass) {
            $contents = str_replace($docClass->getSnippet(), '', $contents);
        }

        $lines = explode("\n", $contents);

        foreach($lines as $key=>$line) {
            $line = trim($line);
            if (StringHelper::startsWith($line, '%')) {
                unset($lines[$key]);
            }
        }

        $contents = implode("\n", $lines);

        $pos = strpos($contents, '\endinput');

        if ($pos !== false) {
            $contents = substr($contents, 0, $pos);
        }

        $pos1 = strpos($contents,'\begin{document}');
        $pos2 = strpos($contents, '\end{document}');

        if ($pos1 !== false AND $pos2 !== false) {
            $contents = substr($contents, $pos1 + 16, $pos2 - $pos1 - 16);
        }

        return $pos === false
            ? $contents
            : substr($contents, 0, $pos);
    }

    /**

     * @return LatexSubFile[]
     */
    public static function getAllSubFilesOf(LatexFile $latexFile, bool $onlyExistingFiles = false): array
    {
        $subFiles = [];

        $contents = $latexFile->getContents();

        $placeholderMgr = new PlaceholderManager();
        $contents = $placeholderMgr->substitutePatterns(LatexPatterns::SIMPLE_LATEX_COMMENTS, $contents);

        foreach(static::SUB_FILE_PATTERNS as $pattern) {

            preg_match_all($pattern['regex'], $contents, $matches);
            foreach($matches[1] as $key=>$name) {

                $name = trim($name);
                $name = str_replace('}', '', $name);

                $snippet = str_replace('@@@', $name, $pattern['snippet']);

                if ($pattern['type'] === self::TYPE_IMPORT) {
                    $snippet = str_replace('@@', $matches[2][$key], $snippet);
                    $name = $matches[2][$key].'/'.$name;
                    $name = str_replace('//', '/', $name);
                }

                $subFile = new static($name, $snippet, $latexFile);

                if ($subFile->fileExists OR !$onlyExistingFiles) {
                    $subFiles[] = $subFile;
                }
            }
        }

        return $subFiles;
    }
}
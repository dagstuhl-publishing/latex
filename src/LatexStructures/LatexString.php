<?php

namespace Dagstuhl\Latex\LatexStructures;

use Dagstuhl\Latex\Parser\LatexParser;
use Dagstuhl\Latex\Parser\ParseTree;
use Dagstuhl\Latex\Scanner\LatexCommandChunk;
use Dagstuhl\Latex\Scanner\LatexCommentChunk;
use Dagstuhl\Latex\Scanner\LatexEnvCommandChunk;
use Dagstuhl\Latex\Scanner\LatexScanner;
use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Utilities\Filesystem;
use Dagstuhl\Latex\Utilities\PlaceholderManager;
use Exception;

class LatexString
{
    public static bool $useParser = true;
    public static bool $writeDiff = false;


    private string $value;
    private string $originalValue;
    protected string $commentFreeCache;
    protected bool $hasValidCommentFreeCache = false;

    protected ?ParseTree $parseTreeCache = NULL;
    protected bool $hasValidParseTreeCache = false;

    private PlaceholderManager $placeholderManager;

    protected ?LatexFile $latexFile;

    public function __construct(?string $value, $latexFile = NULL)
    {
        $this->value = $value ?? '';
        $this->originalValue = $value ?? '';
        $this->latexFile = $latexFile;
        $this->placeholderManager = new PlaceholderManager();
    }

    public function getParseTree(): ?ParseTree
    {
        if (!$this->hasValidParseTreeCache) {
            try {
                $latexParser = new LatexParser();
                $this->parseTreeCache = $latexParser->parse($this->value);
            } catch (Exception $e) {
                $this->parseTreeCache = NULL;
                // echo '-> EXCEPTION: '.$e->getMessage();
            }
            $this->hasValidParseTreeCache = true;
        }

        if ($this->parseTreeCache !== NULL) {
            $rebuiltLatex = $this->parseTreeCache->toLatex();
            if ($rebuiltLatex !== StringHelper::removeCarriageReturns($this->value)) {
                $this->parseTreeCache = NULL;
                if (static::$writeDiff) {
                    Filesystem::put($this->latexFile->getPath('diff.tex'), $rebuiltLatex);
                }
                // echo '-> DIFF';
            }
            elseif (static::$writeDiff) {
                Filesystem::delete($this->latexFile->getPath('diff.tex'));
            }
        }

        return $this->parseTreeCache;
    }

    public function saveVerbatimLikePatterns(): void
    {
        $patterns = LatexPatterns::getVerbatimLikePatterns($this->value);

        $this->value = $this->placeholderManager->substitutePatterns($patterns, $this->value);
    }

    public function restorePatterns(): void
    {
        $this->value = $this->placeholderManager->reSubstitute($this->value);
    }

    private function _removeComments($prettyPrint = true): void
    {
        $this->saveVerbatimLikePatterns();
        $value = $this->value;

        // some special cases
        $value = str_replace('\item%', '\item %', $value);
        $value = str_replace('\par%', '\par %', $value);
        $value = str_replace('\noindent%', '\noindent %', $value);
        $value = str_replace('\textstyle%', '\textstyle %', $value);
        $value = str_replace('\isanewline%', '\isanewline %', $value);
        $value = str_replace('\midrule%', '\midrule %', $value);

        // Step 1: If a comment is followed by a blank (or white-spaced) line,
        //         delete the comment but preserve the blank line.
        $value = preg_replace(LatexPatterns::COMMENT_FOLLOWED_BY_BLANK_LINE, "\n\n", $value);

        // dd($value);

        // Step 2: All remaining comments are deleted including the end-standing linebreak
        //         and whitespaces at the beginning of the next line
        $value = preg_replace(LatexPatterns::COMMENT_AT_END_OF_LINE, '', $value);

        // The following replacements are minor corrections primarily to improve the readability.

        if ($prettyPrint) {
            // Note that Step 2 possibly deletes line breaks after \end{<env>}
            // Add these line-breaks (except there is a tex command right after the environment)
            // to improve readability.
            $value = preg_replace(LatexPatterns::ENV_END_NOT_FOLLOWED_BY_COMMAND, "$1\n$2", $value);

            // Separate new commands by a linebreak if necessary.
            $value = preg_replace(LatexPatterns::NEW_COMMAND_SAME_LINE, "$1\n$2", $value);
        }

        $this->value = $value;
        $this->restorePatterns();

        return;

        // TODO: the following (more accurate) approach is much slower; are there any optimizations?
        $chunks = [];

        while(($chunk = $latexScanner->readChunk()) !== null) {
            if ($chunk instanceof LatexEnvCommandChunk && $chunk->isEnvCommand('begin')) {
                // preserve verbatim-like environments
                if (in_array($chunk->envName, LatexPatterns::CODE_ENVIRONMENT_NAMES)) {
                    $chunk = $latexScanner->readEnv($chunk);
                }
                // skip comment environments
                if ($chunk->envName === 'comment') {
                    $latexScanner->readEnv($chunk);
                    $chunk = null;
                }
            }

            if ($chunk !== null) {
                $chunks[] = $chunk;
            }
            // preserve inline verbatim strings
            if ($chunk instanceof LatexCommandChunk && $chunk->isCommand('verb')) {
                $chunks[] = $latexScanner->readVerb();
            }
        }

        $this->value = '';
        foreach ($chunks as $chunk) {
            if (!$chunk instanceof LatexCommentChunk) {
                $this->value .= $chunk->raw;
            }
        }
    }

    public function removeComments($prettyPrint = true): void
    {
        $this->setValue($this->getValueWithoutComments($prettyPrint));
    }

    public function getValueWithoutComments(bool $prettyPrint = false): string
    {
        if (empty($this->commentFreeCache) OR !$this->hasValidCommentFreeCache) {
            $clonedString = new self($this->value);
            $clonedString->_removeComments($prettyPrint);
            $this->commentFreeCache = $clonedString->getValue(false, false);
            $this->hasValidCommentFreeCache = true;
        }

        return $this->commentFreeCache;
    }

    protected function setLatexFile($latexFile): void
    {
        $this->latexFile = $latexFile;
    }

    public function getLatexFile(): ?LatexFile
    {
        return $this->latexFile;
    }

    public function isWritableToLatexFile(): bool
    {
        return $this->latexFile instanceof LatexFile
            AND substr_count($this->latexFile->getValue(), $this->originalValue) === 1;
    }

    public function getValue($withoutComments = false, $prettyPrint = true): string
    {
        return $withoutComments
            ? $this->getValueWithoutComments($prettyPrint)
            : $this->value;
    }

    public function setValue($value, $writeToParents = false): bool
    {
        if ($this->value !== $value) {
            $this->hasValidCommentFreeCache = false;
            $this->hasValidParseTreeCache = false;
        }

        $this->value = $value;

        if ($writeToParents AND $this->isWritableToLatexFile()) {

            $contents = $this->latexFile->getContents();
            $contents = str_replace($this->originalValue, $value, $contents);
            $this->latexFile->setContents($contents);

            return true;
        }

        return false;
    }

    /**
     * @return LatexCommand[]
     */
    public function getCommands(): array
    {
        return LatexCommand::getCommands($this);
    }

    /**
     * @return LatexEnvironment[]
     */
    public function getEnvironments(string $name): array
    {
        if (static::$useParser) {
            $parseTree = $this->getParseTree();

            if ($parseTree !== NULL) {
                $envs = $parseTree->getEnvironments($name);
                foreach ($envs as $env) {
                    $env->_log['source'] = 'parser';
                    $env->setLatexFile($this->latexFile);
                }
                return $envs;
            }
        }

        $contents = $this->getValue(true);

        return LatexEnvironment::_getEnvironments($name, $contents, $this->latexFile);
    }

    public function getEnvironment(string $name): ?LatexEnvironment
    {
        $environments = $this->getEnvironments($name);

        return count($environments) === 1
            ? $environments[0]
            : NULL;
    }

    /**
     * @return LatexMacro[]
     */
    public function getMacros(string $name): array
    {
        if (static::$useParser && !strpos($name, '*')) {
            $parseTree = $this->getParseTree();
            if ($parseTree !== NULL) {
                $macros = $parseTree->getMacros($name);
                // filter to reproduce former behavior (i.e., macro has per definition at least one argument)
                $macros = array_filter($macros, fn (LatexMacro $m) => count($m->getArguments()) > 0);
                foreach ($macros as $macro) {
                    $macro->_log['source'] = 'parser';
                    $macro->setLatexFile($this->latexFile);
                }
                return $macros;
            }
        }

        $contents = $this->getValue(true, false);

        return LatexMacro::_getMacros($name, $contents, $this->latexFile);
    }

    public function getMacro(string $name): ?LatexMacro
    {
        $macros = $this->getMacros($name);

        return count($macros) === 1
            ? $macros[0]
            : NULL;
    }

    public function hasMacro(string $name): bool
    {
        return count($this->getMacros($name)) > 0;
    }

    /**
     * @return string[]
     */
    public function getUsedPackages(?array &$options = []): array
    {
        $usedPackages = [];

        $packages = $this->getMacros('usepackage');

        foreach ($packages as $package) {
            $opt = $package->getOptions();
            $packageNames = $package->getArguments();
            $packageNames = explode(',', $packageNames[0]);

            foreach ($packageNames as $name) {
                $usedPackages[] = trim($name);
                $options[] = $opt;
            }
        }

        return $usedPackages;
    }

    /**
     * @return LatexMacro[]
     */
    public function getHeadingsWithMaths(): array
    {
        $headlineNames = [
            'section', 'section*',
            'subsection', 'subsection*',
            'subsubsection', 'subsubsection*',
            'paragraph', 'paragraph*',
            'subparagraph', 'subparagraph*',
            'textbf'
        ];

        $mathHeadlines = [];

        $tables = $this->getEnvironments('table');

        $contents = $this->value;

        foreach($tables as $table) {
            $contents = str_replace($table->getContents(), '', $contents);
        }

        foreach($headlineNames as $headlineName) {
            $macros = $this->getMacros($headlineName);

            foreach($macros as $key=>$macro) {
                if (!$macro->contains('$')) {
                    unset($macros[$key]);
                }
            }

            $mathHeadlines = array_merge($mathHeadlines, $macros);
        }

        return $mathHeadlines;
    }

    /**
     * @param string[] $commands
     * @param callable $transformChunkToRaw
     * @param int $maxIterations
     * @return static
     */
    public function mapCommands(string|array $commands, callable $transformChunkToRaw, int $maxIterations = 6): static
    {
        if (is_string($commands)) {
            $commands = [ $commands ];
        }

        $scanner = new LatexScanner($this->value);

        $newString = '';
        $occurrenceCount = 0;
        while(($chunk = $scanner->readChunk()) !== null) {
            $value = $chunk->raw;

            if ($chunk->isCommand($commands)) {
                $occurrenceCount++;
                $value = $transformChunkToRaw($chunk, $scanner);
            }

            $newString .= $value;
        }

        $this->value = $newString;

        if ($occurrenceCount > 0 && $maxIterations > 0) {
            return $this->mapCommands($commands, $transformChunkToRaw, --$maxIterations);
        }

        return $this;
    }
}
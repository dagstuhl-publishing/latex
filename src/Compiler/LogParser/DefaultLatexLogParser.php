<?php

namespace Dagstuhl\Latex\Compiler\LogParser;

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;

class DefaultLatexLogParser implements LogParserInterface
{
    const LOG_FILTER_FULL = 'full';
    const LOG_FILTER_STANDARD = 'standard';
    const LOG_FILTER_STANDARD_BIB = 'bib-standard';

    const LOG_FILTER_REGEX = [
        self::LOG_FILTER_STANDARD => '/^\!|Undefined control sequence|latex warning|Overfull \\\\hbox/i',
        self::LOG_FILTER_STANDARD_BIB => '/^warning(?!\$ \-\-)|error|unbalanced braces| bad |'.
            ' missing |I found no |repeated entry|I couldn\'t |'.
            'illegal end of |illegal\, |I was expecting/i'
    ];

    const MESSAGE_TYPE_ERROR = 'error-msg';
    const MESSAGE_TYPE_WARNING = 'warning-msg';

    const MESSAGES = [
        self::MESSAGE_TYPE_ERROR => [
            [ 'regex' => '/undefined reference/',       'msg' => 'ERROR: There are undefined references.' ],
            [ 'regex' => '/multiply-defined label/',    'msg' => 'ERROR: There are multiply-defined labels.' ],
            [ 'regex' => '/Error\: File (.*) not found/', 'msg' => 'ERROR: There are missing files.' ],
            [ 'regex' => '/string name \"(.*)\" is undefined/', 'msg' => 'ERROR: There are undefined strings in bib-file.' ],
            [ 'regex' => '/illegal end of database file/', 'msg' => 'ERROR: Illegal end of database file.' ],
            [ 'regex' => '/undefined citation/',        'msg' => 'ERROR: There are undefined citations.' ],
            [ 'regex' => '/natbib Warning\: Citation (.*) undefined/', 'msg' => 'ERROR: There are undefined citations.' ],
            [ 'regex' => '/biblatex Warning: (.*)/', 'msg' => 'ERROR: Biblatex is currently not supported. Please use bibtex instead.' ]
        ],

        self::MESSAGE_TYPE_WARNING => [
            [ 'regex' => '/Warning--/', 'msg' => 'WARNING: There are bibtex warnings. - Please resolve, if possible.' ],
            [ 'regex' => '/Illegal, another \\\\bibstyle command/', 'msg' => 'There are several \bibstyle commands. Please use <code>\bibstyle{plainurl}</code> (once).' ]
        ]
    ];

    protected LatexFile $latexFile;

    public function __construct(LatexFile $latexFile)
    {
        $this->latexFile = $latexFile;
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private function getLatexLogRaw(array $lines): array
    {
        $printLine = false;
        $outputLines = [];
        $output = [];

        foreach ($lines as $line) {

            $line = trim($line);

            if (preg_match(self::LOG_FILTER_REGEX[self::LOG_FILTER_STANDARD], $line) > 0) {
                $printLine = true;
                $output[] = '* ' . $line;
            } elseif ($printLine) {
                if ($line !== '') {
                    $output[] = $line;
                }
            }

            // overfull boxes are single-line messages
            if ($line === '' OR preg_match('/Overfull \\\\hbox|undefined on/i', $line) > 0) {
                if ($printLine) {
                    $printLine = false;
                    $outputLines[] = implode('', $output);
                    $output = [];
                }
            }
        }

        return $outputLines;
    }

    /**
     * @return array|string[]
     */
    public function getLatexLog(?string $logFilter = self::LOG_FILTER_STANDARD): array
    {
        $logFilter = $logFilter ?? self::LOG_FILTER_STANDARD;
        $pathToLogFile = $this->latexFile->getPath('log');

        try {
            $file = Filesystem::get($pathToLogFile);
            $lines = explode("\n", $file);

        } catch (\Exception $e) {
            return [ 'ERROR: Could not open latex log file: '.$pathToLogFile ];
        }

        $outputLines = [ 'Please specify a log-filter for LatexCompiler (see LatexCompiler::getLatexLog)' ];

        if ($logFilter === self::LOG_FILTER_STANDARD) {

            // first step: apply log-filter to generate $outputLines array
            $outputLines = $this->getLatexLogRaw($lines);

            // second step: group warnings/errors in $outputlines
            $referenceWarnings = [];
            $citationWarnings = [];
            $essentialWarnings = [];

            foreach($outputLines as $line) {
                if (stripos($line, 'latex warning: citation')) {
                    $citationWarnings[] = $line;
                }
                elseif (stripos($line, 'latex warning: reference')) {
                    $referenceWarnings[] = $line;
                }
                else {
                    $essentialWarnings[] = $line;
                }
            }

            if (count($referenceWarnings) > 8) {
                $missingReferences = [];
                foreach($referenceWarnings as $warning) {
                    preg_match('/Reference [`\'](.*)\' on page/', $warning, $match);
                    if (isset($match[1])) {
                        $missingReferences[] = $match[1];
                    }
                }

                $missingReferences = array_values(array_unique($missingReferences));

                $referenceWarnings = [
                    '* LaTeX Warning: many undefined references (' . count($missingReferences) .
                    '), possibly since a former LaTeX pass failed: '
                ];
                $referenceWarnings[] = '   ' . implode('; ', array_slice($missingReferences, 0, 5)) . ' ...';
            }

            if (count($citationWarnings) > 8) {
                $missingCitations = [];
                foreach($citationWarnings as $warning) {
                    preg_match('/Citation [`\'](.*)\' on page/', $warning, $match);
                    if (isset($match[1])) {
                        $missingCitations[] = $match[1];
                    }
                }

                $missingCitations = array_values(array_unique($missingCitations));

                $citationWarnings = [
                    '* LaTeX Warning: many undefined citations (' . count($missingCitations) .
                    '), possibly since a former LaTeX pass failed, or a bib-file is missing: '
                ];
                $citationWarnings[] = '   ' . implode('; ', array_slice($missingCitations, 0, 5)) . ' ...';
            }


            $outputLines = array_merge($essentialWarnings, $referenceWarnings, $citationWarnings);
        }
        elseif ($logFilter === self::LOG_FILTER_FULL) {
            $outputLines = $lines;
        }

        return $outputLines;
    }

    /**
     * @param string[] $lines
     * @return array|string[]
     */
    private function getBibTexLogRaw(array $lines): array
    {
        $printLine = false;
        $outputLines = [];
        $output = [];

        foreach ($lines as $line) {

            $line = trim($line);

            if (preg_match(self::LOG_FILTER_REGEX[self::LOG_FILTER_STANDARD_BIB], $line) > 0) {
                $printLine = true;
                $output[] = '* ' . $line;
            } elseif ($printLine) {
                if ($line !== '') {
                    $output[] = $line;
                }
            }

            if ($line === '' OR preg_match('/^\-\-/', $line) === 0) {
                if ($printLine) {
                    $printLine = false;
                    $outputLines[] = implode(' | ', $output);
                    $output = [];
                }
            }
        }

        return $outputLines;
    }

    /**
     * @return array|string[]
     */
    public function getBibTexLog(?string $logFilter = self::LOG_FILTER_STANDARD_BIB): array
    {
        $logFilter = $logFilter ?? self::LOG_FILTER_STANDARD_BIB;

        if (count($this->latexFile->getBibliography()->getPathsToUsedBibFiles()) === 0) {
            return [ '* No bib-file used.' ];
        }

        $pathToLogFile = $this->latexFile->getPath('blg');

        try {
            $file = Filesystem::get($pathToLogFile);
            $lines = explode("\n", $file);

        } catch (\Exception $e) {
            return [ '* ERROR: Could not open bibtex log file: '.$pathToLogFile ];
        }

        $outputLines[] = [ '* Please specify a valid log-filter for LatexCompiler (see LatexCompiler::getBibTexLog)' ];

        if ($logFilter === self::LOG_FILTER_STANDARD_BIB) {

            // first step: apply log-filter to generate $outputLines array
            $outputLines = $this->getBibTexLogRaw($lines);

            // second step: group warnings/errors in $outputLines
            //   first group: I didn't find a database entry for ...
            //   second group: all the rest
            $missingEntryWarnings = [];
            $essentialWarnings = [];

            foreach($outputLines as $line) {
                if (stripos($line, 'I didn\'t find a database entry for ') !== false) {
                    $missingEntryWarnings[] = $line;
                }
                else {
                    $essentialWarnings[] = $line;
                }
            }

            if (count($missingEntryWarnings) > 8) {
                $missingReferences = [];
                foreach($missingEntryWarnings as $warning) {
                    preg_match('/I didn\'t find a database entry for \"(.*)\"/', $warning, $match);
                    if (isset($match[1])) {
                        $missingReferences[] = $match[1];
                    }
                }

                $missingReferences = array_values(array_unique($missingReferences));

                $missingEntryWarnings = [
                    '* Warning--I didn\'t find a database entry in '.count($missingReferences).' cases'.
                    ', possibly due to a missing bib-file: '
                ];
                $missingEntryWarnings[] = '   '.implode('; ', array_slice($missingReferences, 0,5)).' ...';
            }

            $outputLines = array_merge($essentialWarnings, $missingEntryWarnings);
        }
        elseif ($logFilter === self::LOG_FILTER_FULL) {
            $outputLines = $lines;
        }

        return $outputLines;
    }

    /**
     * @return array|string[]
     */
    public function getMessages(string $messageType): array
    {
        $log = implode("\n", $this->getLatexLog()) ."\n". implode("\n", $this->getBibTexLog());

        $errorMsg = [];

        foreach(self::MESSAGES[$messageType] as $error) {
            $matches = [];

            preg_match_all($error['regex'], $log, $matches);

            if (count($matches[0]) > 0) {
                $errorMsg[] = $error['msg'];
            }
        }

        return array_unique($errorMsg);
    }
}

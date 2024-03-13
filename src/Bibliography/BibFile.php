<?php

namespace Dagstuhl\Latex\Bibliography;

use Exception;
use Throwable;
use Dagstuhl\Latex\Strings\StringHelper;

class BibFile
{
    private string $path;

    /** @var string[][] */
    private array $log = [];

    /** @var BibEntry[]|null */
    private ?array $allEntries = NULL;

    /** @var BibEntry[]|null */
    private ?array $usedEntries = NULL;

    /** @var BibEntry[]|null */
    private ?array $orderedEntries = NULL;

    /** @var string[]|null  */
    private ?array $usedDois = [];

    /** @var string[]|null  */
    private ?array $usedBibUrls = [];

    /** @var string[] */
    private array $multiplyUsedDois = [];

    /** @var string[] */
    private array $multiplyUsedBibUrls = [];

    /** @var string[] */
    private ?array $keys = NULL;

    private ?BblFile $bblFile = NULL;
    private ?Bibliography $bibliography = NULL;
    private bool $logParser = false;
    private bool $crossRefMerge = true;

    public function __construct(string $path, BblFile|Bibliography|NULL $bblFileOrBibliography = NULL)
    {
        $this->path = $path;

        $this->setAllEntries();

        if ($bblFileOrBibliography !== NULL) {

            if ($bblFileOrBibliography instanceof BblFile) {
                $this->bblFile = $bblFileOrBibliography;
            }

            if ($bblFileOrBibliography instanceof Bibliography) {
                $this->bibliography = $bblFileOrBibliography;
                $this->bblFile = $bblFileOrBibliography->getBblFile();
            }

            $this->setKeysByBblFile();
        }
    }

    public function setCrossRefMerge(bool $value): void
    {
        $this->crossRefMerge = $value;
    }

    public function setLogParser(bool $value): void
    {
        $this->logParser = $value;
    }

    public function getBblFile(): BblFile
    {
        return $this->bblFile;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string[]
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * @return BibEntry[]
     */
    public function getAllBibEntries(): array
    {
        return $this->allEntries;
    }

    /**
     * @return BibEntry[]
     */
    public function getUsedBibEntries(bool $cleanUp = false): array
    {
        /** $cleanUp === false: read raw data */
        if ($cleanUp === false) {
            if ($this->bblFile === NULL) {
                $this->usedEntries = $this->getAllBibEntries();
            }
            else {
                $this->usedEntries = [];
                foreach ($this->bblFile->getKeys() as $key) {
                    $item = $this->getFirstBibEntry($key);
                    if ($item !== NULL) {
                        $this->usedEntries[] = $item;
                    }
                }
            }
        }

        /** Dagstuhl Publishing internal cleanup */
        else {
            if ($this->usedEntries === NULL and $this->keys !== NULL) {
                $this->setUsedBibEntries();
            } elseif ($this->keys === NULL) {
                $msg = 'CAUTION: no keys set! You can use "setKeysByBblPath" or "setKeysByArray" methods to set them.';
                $this->writeLog(BibEntry::LOG_OUTPUT, $msg, BibEntry::LOG_ALERT);
            }
        }

        return $this->usedEntries;
    }

    /**
     * @return BibEntry[]
     */
    public function getOrderedBibEntries(): array
    {
        if ($this->orderedEntries === NULL) {
            $this->setOrderedEntries();
        }

        return $this->orderedEntries;
    }

    public function getFirstBibEntry(string $key): ?BibEntry
    {
        $entries = [];
        foreach ($this->allEntries as $entry) {

            if (strcasecmp($entry->getKey(), $key) === 0) {
                $entries[] = $entry;
            }
        }

        if (count($entries) === 0) {
            $msg = 'CAUTION: no entry with key '. $key;
            $this->writeLog(BibEntry::LOG_OUTPUT, $msg, BibEntry::LOG_ALERT);
            return NULL;
        }

        if (count($entries) > 1) {
            $this->writeLog(BibEntry::LOG_OUTPUT, 'CAUTION: more than 1 entry found with key '. $key, BibEntry::LOG_ALERT);
        }

        return $entries[0];
    }

    /**
     * @param string $path
     * @throws Exception
     */
    public function setKeysByBblPath(string $path): void
    {
        $this->bblFile = new BblFile($path);
        $this->setKeysByBblFile();
    }

    private function setKeysByBblFile(BblFile $bblFile = NULL): void
    {
        if ($bblFile === NULL) {
            $bblFile = $this->bblFile;
        }

        $this->keys = $bblFile->getKeys();
        $this->setUsedBibEntries();
    }

    public function setKeysByArray(array $array): void
    {
        $this->keys = $array;
        $this->setUsedBibEntries();
    }

    /**
     * fills $this->allEntries with an BibEntry-array of every entry that is mentioned in this BibFile
     */
    private function setAllEntries(): void
    {
        $this->allEntries = [];

        $bibParser = new ParseEntries($this->logParser);

        $bibParser->setExpandMacro(true);
        $bibParser->loadStringMacro(BibEntry::JOURNAL_ABBREVIATIONS + self::getMonthArray());

        $bibParser->openBib($this->path);

        try {
            $bibParser->extractEntries();
            list($preamble, $strings, $entries, $undefinedStrings) = $bibParser->returnArrays();
        }
        catch(Throwable $ex) {

            $this->writeLog(BibEntry::LOG_OUTPUT, $ex->getMessage(), BibEntry::LOG_ALERT);
            //var_dump($ex->getMessage());
            //exit();
            throw $ex;
        }

        $bibParser->closeBib();

        $this->mergeLogs($bibParser);

        foreach ($entries as $entry) {
            $this->allEntries[] = new BibEntry($entry);
        }
    }

    private function collectDoiAndBibUrl(BibEntry $entry): void
    {
        $doi = $entry->getField('doi');

        if (!empty($doi)) {

            $doi = BibEntry::cleanupDoi($doi);

            if (!in_array($doi, $this->usedDois)) {
                $this->usedDois[] = $doi;
            } else {
                $this->multiplyUsedDois[] = $doi;
            }
        }

        $bibUrl = $entry->getField('biburl');

        if (!empty($bibUrl)) {
            if (!in_array($bibUrl, $this->usedBibUrls)) {
                $this->usedBibUrls[] = $bibUrl;
            }
            else {
                $this->multiplyUsedBibUrls[] = $bibUrl;
            }
        }
    }

    /**
     * @param Bibentry[] $bibEntries
     */
    private function removeDoiAndBibUrlDoublets(array $bibEntries): void
    {
        foreach($this->multiplyUsedDois as $multipleDoi) {
            $affectedKeys = [];
            foreach($bibEntries as &$entry) {
                $doi = BibEntry::cleanupDoi($entry->getField('doi') ?? '');

                if ($doi === $multipleDoi) {
                    $entry->renameField('doi', '_doi');
                    $affectedKeys[] = $entry->getKey();
                }
            }

            if (count($affectedKeys) > 0) {
                $this->writeLog(
                    BibEntry::LOG_CHANGES,
                    'CAUTION: same doi [' . $multipleDoi . '] used for several entries: ' . implode('; ', $affectedKeys) .
                    ' - removed this doi from all these entries.',
                    BibEntry::LOG_ALERT
                );
            }
        }

        foreach($this->multiplyUsedBibUrls as $multipleBibUrl) {
            $affectedKeys = [];
            foreach($bibEntries as &$entry) {
                $bibUrl = $entry->getField('biburl');

                if ($bibUrl === $multipleBibUrl) {
                    $entry->renameField('biburl', '_biburl');
                    $affectedKeys[] = $entry->getKey();
                }
            }

            $this->writeLog(
                BibEntry::LOG_CHANGES,
                'CAUTION: same biburl ['. $multipleBibUrl .'] used for several entries: ' . implode('; ', $affectedKeys).
                ' - removed this biburl from all these entries.',
                BibEntry::LOG_ALERT
            );
        }
    }

    /**
     * reduces (based on $this->allEntries) to the BibEntries
     * that are used (according to bbl-file)
     * and saves as array of BibEntries in $this->usedEntries
     */
    private function setUsedBibEntries(): void
    {
        $this->multiplyUsedDois = [];
        $this->multiplyUsedBibUrls = [];
        $this->usedDois = [];
        $this->usedBibUrls = [];

        $this->writeLog(BibEntry::LOG_CHANGES, 'Removing unreferenced bibtex entries and reordering according order in bbl');
        $this->usedEntries = [];

        foreach ($this->keys as $key) {
            $entry = $this->getFirstBibEntry($key);

            if ($entry !== NULL) {
                $this->usedEntries[] = $entry;
                $this->collectDoiAndBibUrl($entry);

                if ($entry->hasField('crossref')) {

                    $entry = $this->getFirstBibEntry($entry->getField('crossref'));

                    if ($entry !== NULL) {
                        $this->usedEntries[] = $entry;
                        $this->collectDoiAndBibUrl($entry);
                    }
                }
            }
        }

        $this->removeDoiAndBibUrlDoublets($this->usedEntries);
    }

    /**
     * merge crossref information of all $this->usedEntries
     */
    public function mergeCrossRefs(): void
    {
        foreach ($this->usedEntries as $entry) {

            if (!$entry->hasCrossRef()) {
                continue;
            }

            $crossKey = $entry->getCrossRefKey();

            $foundCross = false;

            foreach ($this->allEntries as $rawEntry) {

                $rawEntryKey = $rawEntry->getKey();

                if (!empty($rawEntryKey) AND $entry->hasCrossRef() AND strcasecmp(trim($rawEntryKey), $entry->getCrossRefKey()) === 0) {

                    if ($foundCross) {

                        $this->writeLog(BibEntry::LOG_CHANGES, "resolved: Multiple (cross-referenced) entries for key $crossKey; used only first found entry!");
                    }
                    else {

                        $foundCross = true;

                        if ($this->crossRefMerge) {

                            $entry->renameField('crossref', '_crossref');

                            $inherited = false;

                            foreach ($rawEntry->getFields() as $keyCross => $valCross) {

                                if (!$entry->hasField($keyCross)) {

                                    $inherited = true;
                                    $entry->setField($keyCross, $valCross);
                                    $this->writeLog(BibEntry::LOG_CHANGES, "Inherited value of '$keyCross' from cross-referenced entry to entry ".$entry->getKey());
                                }
                            }

                            $this->writeCrossRefMergeMsg($inherited, $entry->getKey());
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $inherited bool
     * @param $key string
     */
    protected function writeCrossRefMergeMsg(bool &$inherited, string $key): void
    {
        if ($inherited) {
            $msg = "Removed cross-reference for entry '$key' and inherited missing values from cross-referenced entry";
        }
        else {
            $msg = "Removed cross-reference for entry '$key'";
        }

        $this->writeLog(BibEntry::LOG_CHANGES, $msg);
    }

    /**
     * uses only the entries which are set in $this->keys and writes "_order" to each
     * while saving them in $this->orderedEntries
     */
    protected function setOrderedEntries(): void
    {
        $this->multiplyUsedDois = [];
        $this->multiplyUsedBibUrls = [];
        $this->usedDois = [];
        $this->usedBibUrls = [];

        $this->writeLog(BibEntry::LOG_OUTPUT, "Setting _order (according to bbl-file) & checking uniqueness of DOIs and BibUrls ...");

        $orderNumber = 1;

        foreach ($this->usedEntries as &$entry) {

            $lowerCaseKeys = array_map('strtolower', $this->keys);

            if (in_array(strtolower($entry->getKey()), $lowerCaseKeys)) {
                // TODO: crossref in crossref
                /*
                 //check if entry crossreferenced another entry
                    if (isset($entryCross['crossref'])) {

                        $msg = "CAUTION: cross-referenced entry contains another cross-reference: " . $crossKey . " --> " . $entryCross['crossref'] . "!";
                        $this->writeLog(BibEntry::LOG_CHANGES, $msg, BibEntry::LOG_ALERT);
                    }
                 */
                $entry->setField('_order', $orderNumber, true);
                $this->orderedEntries[] = $entry;

                $this->collectDoiAndBibUrl($entry);

                $orderNumber++;
            }
            else {
                $this->writeLog(BibEntry::LOG_OUTPUT, 'NOTE: Key not found, when ordering entries: '.$entry->getKey().' - Most likely a removed cross reference. Check bibtex log for errors/warnings.', BibEntry::LOG_EMPH);
            }
        }

        $this->removeDoiAndBibUrlDoublets($this->orderedEntries);
    }

    /**
     * @throws Exception
     */
    public function fillMissingFields(): void
    {
        foreach ($this->usedEntries as $entry) {

            $entry->setMissingMandatoryFields();
            $this->mergeLogs($entry);

            if ($entry->hasMissingFields()) {

                if ($entry->hasNonEmptyField('doi') AND StringHelper::startsWith($entry->getField('doi'), '10.')) {

                    try {
                        $dblpEntry = BibEntry::fromDblpByDoi($entry->getField('doi'), $entry->getKey());
                        $this->writeLog(BibEntry::LOG_OUTPUT, $entry->getKey() .': PULL FROM DBLP BY DOI');
                    }
                    catch (Exception $ex) {
                        $this->writeLog(BibEntry::LOG_OUTPUT, $ex->getMessage(), BibEntry::LOG_ALERT);
                        throw $ex;
                    }

                    if ($entry->merge($dblpEntry)) {
                        $this->writeLog(BibEntry::LOG_OUTPUT, 'Added fields for (some) entries from current dblp entries');
                    }

                    $this->mergeLogs($dblpEntry);
                }
                elseif ($entry->hasNonEmptyField('doi') AND !StringHelper::startsWith($entry->getField('doi'), '10.')) {

                    throw new Exception("CAUTION: DOI ". $entry->getField('doi') ." doesn't start with '10.'!!! Please check!");
                }
                elseif ($entry->hasNonEmptyField('biburl')
                    AND $entry->hasNonEmptyField('bibsource')
                    AND str_contains($entry->getField('bibsource'), 'dblp')) {

                    try {
                        $dblpEntry = BibEntry::fromDblpByBibUrl($entry->getField('biburl'), $entry->getKey());
                        $this->writeLog(BibEntry::LOG_OUTPUT, $entry->getKey() .': PULL FROM DBLP BY BIBURL');
                    }
                    catch (Exception $ex) {
                        $this->writeLog(BibEntry::LOG_OUTPUT, $ex->getMessage(), BibEntry::LOG_ALERT);
                        throw $ex;
                    }

                    $entry->merge($dblpEntry);
                    $this->mergeLogs($dblpEntry);
                }
            }
        }
    }

    /**
     * @param string[]|null $fields
     *
     * Revises & corrects used entries
     */
    public function reviseEntries(array $fields = NULL): void
    {
        foreach ($this->usedEntries as $entry) {

            $entry->reviseFields($fields);
            $this->mergeLogs($entry);
        }
    }

    /**
     * Corrects doi, url, eprint, ee, and note of used entries
     */
    public function cleanElectronicReferences(): void
    {
        foreach ($this->usedEntries as $entry) {

            // TODO: disableAllReferences in case more than 1 entry uses same doi
            $entry->cleanupElectronicReferences();
            $this->mergeLogs($entry);
        }

        $this->removeDoiAndBibUrlDoublets($this->usedEntries);
    }

    public function cleanArxivReferences(bool $debug = true): void
    {
        if ($debug) {
            $this->writeLog(BibEntry::LOG_CHANGES, 'NOTE: debug-mode activated for arxiv-cleaning, i.e. every change will be marked with a "'. BibEntry::DEBUG_MARK .'"', BibEntry::LOG_EMPH);
        }

        foreach ($this->usedEntries as $entry) {

            $entry->cleanArxivReferences($debug);
            $this->mergeLogs($entry);
        }
    }

    private static function getMonthArray(): array
    {
        $months = array_keys(BibEntry::MONTH_MAPPING);
        $array = [];

        foreach ($months as $key => $value) {
            $array[$value] = $value;
        }

        return $array;
    }

    // ----- LOGGING -----

    /**
     * @param string $channel
     * @param string $msg
     * @param string|NULL $style
     */
    private function writeLog(string $channel, string $msg, string $style = NULL): void
    {
        if (!isset($this->log[$channel])) {
            $this->log[$channel] = [];
        }

        $this->log[$channel][] = [ 'msg' => $msg, 'style' => $style ];
    }

    private function mergeLogs(ParseEntries|BibEntry $instance): void
    {
        $this->log = array_merge_recursive($this->log, $instance->getLog());
        $instance->clearLog();
    }

    /**
     * @return array|string[]|string[][]
     */
    public function getLog(string $channel = NULL): array
    {
        if ($channel === NULL) {
            return $this->log;
        }

        if (isset($this->log[$channel])) {
            return $this->log[$channel];
        }

        return [];
    }

    public function clearLog(string $channel = NULL): void
    {
        if ($channel === NULL) {
            $this->log = [];
        }

        if (isset($this->log[$channel])) {
            $this->log[$channel] = [];
        }
    }

    // TODO: add new "collectItems" method, resp. create blacklist (collectItems) & remove entries (cleanBibTex) in one method!
}

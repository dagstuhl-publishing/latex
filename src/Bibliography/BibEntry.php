<?php

namespace Dagstuhl\Latex\Bibliography;

use Exception;
use Dagstuhl\Latex\Strings\StringHelper;
//use Log;

class BibEntry
{
    const DBLP_API_SLEEP_TIME = 2; // in seconds
    const DBLP_API_URL = 'https://dblp.dagstuhl.de/doi/bib/';
    const DBLP_SEARCH_API_URL = 'https://dblp.org/search/publ/api';

    const LOG_OUTPUT = 'output';
    const LOG_CHANGES = 'changes';
    const LOG_PARSER = 'parser';
    const LOG_ALERT = 'alert';
    const LOG_EMPH = 'emph';

    const DEBUG_MARK = '+';

    // definitions according https://en.wikipedia.org/wiki/BibTeX#Field_types
    const MANDATORY_BIBTEX_FIELDS = [
        'article' => ['author', 'title', 'journal', 'year'],
        'book' => ['title', 'publisher', 'year'],
        'booklet' => ['title'],
        'conference' => ['author', 'title', 'booktitle', 'year'],
        'inbook' => ['title', 'pages', 'publisher', 'year'],
        'incollection' => ['author', 'title', 'booktitle', 'year'],
        'inproceedings' => ['author', 'title', 'booktitle', 'year'],
        'manual'  => ['title'],
        'mastersthesis' => ['author', 'title', 'school', 'year'],
        'misc' => [],
        'phdthesis' => ['author', 'title', 'school', 'year'],
        'proceedings' => ['title', 'year'],
        'techreport' => ['author', 'title', 'institution', 'year'],
        'unpublished' => ['author', 'title', 'note']
    ];

    const DOI_PREFIXES = [
        'http://doi.acm.org/',
        'http://dx.doi.org/',
        'http://doi.ieeecomputersociety.org/',
        'https://doi.org/'
    ];

    const JOURNAL_ABBREVIATIONS = [
        'acmcs' => 'ACM Computing Surveys',
        'acta' => 'Acta Informatica',
        'cacm' => 'Communications of the ACM',
        'ibmjrd' => 'IBM Journal of Research and Development',
        'ibmsj' => 'IBM Systems Journal',
        'ieeese' => 'IEEE Transactions on Software Engineering',
        'ieeetc' => 'IEEE Transactions on Computers',
        'ieeetcad' => 'IEEE Transactions on Computer-Aided Design of Integrated Circuits',
        'ipl' => 'Information Processing Letters',
        'jacm' => 'Journal of the ACM',
        'jcss' => 'Journal of Computer and System Sciences',
        'scp' => 'Science of Computer Programming',
        'sicomp' => 'SIAM Journal on Computing',
        'tocs' => 'ACM Transactions on Computer Systems',
        'tods' => 'ACM Transactions on Database Systems',
        'tog' => 'ACM Transactions on Graphics',
        'toms' => 'ACM Transactions on Mathematical Software',
        'toois' => 'ACM Transactions on Office Information Systems',
        'toplas' => 'ACM Transactions on Programming Languages and Systems',
        'tcs' => 'Theoretical Computer Science'
    ];

    const MONTH_MAPPING = [
        'jan' => [ 'jan', 'jan.', 'january', '1', '01' ],
        'feb' => [ 'feb', 'feb.', 'february', '2', '02' ],
        'mar' => [ 'mar', 'mar.', 'march', '3', '03' ],
        'apr' => [ 'apr', 'apr.', 'april', '4', '04' ],
        'may' => [ 'may', '5', '05' ],
        'jun' => [ 'jun', 'jun.', 'june', '6', '06' ],
        'jul' => [ 'jul', 'jul.', 'july', '7', '07' ],
        'aug' => [ 'aug', 'aug.', 'august', '8', '08' ],
        'sep' => [ 'sep', 'sep.', 'sept', 'sept.', 'september', '9', '09' ],
        'oct' => [ 'oct', 'oct.', 'october', '10' ],
        'nov' => [ 'nov', 'nov.', 'november', '11' ],
        'dec' => [ 'dec', 'dec.', 'december', '12' ]
    ];

    const MONTH_NAMES = [
        'jan' => 'January',
        'feb' => 'February',
        'mar' => 'March',
        'apr' => 'April',
        'may' => 'May',
        'jun' => 'June',
        'jul' => 'July',
        'aug' => 'August',
        'sep' => 'September',
        'oct' => 'October',
        'nov' => 'November',
        'dec' => 'December'
    ];

    protected string $type;
    protected string $key;

    /**  @var string[] */
    protected array $fields = [];

    /** @var string[] */
    protected array $redundantFields = [];

    /** @var string[] */
    protected array $missingFields = [];

    /** @var string[][] */
    protected array $log = [];
    protected bool $debug = true;

    private static int $lastDblpQuery = 0;
    private static int $lastDblpSearchQuery = 0;

    /**
     * BibEntry constructor.
     * @param string[]|string|null $entry
     */
    public function __construct(array|string $entry = NULL)
    {
        if ($entry !== NULL) {

            if (is_string($entry)) {

                preg_match('/^\s*@(.*)\{(.*),$(\s*(.*),)*\s*\}/m', $entry, $match);

                $this->type = $match[1] ?? 'unknownType';
                $this->key = $match[2] ?? 'unknownKey';

                preg_match_all('/\s*(.*)\s\=\s*\{(.*)\}/', $entry, $fields);

                $fieldKeys = $fields[1];
                $fieldValues = $fields[2];

                foreach ($fieldKeys as $index => $fieldKey) {

                    $fieldKey = trim($fieldKey);

                    // avoid that a field value is overwritten since BibTex ignores values of repeated fields
                    if (!isset($fields[$fieldKey])) {
                        $this->fields[$fieldKey] = $fieldValues[$index];
                    }
                }
            }
            elseif (is_array($entry)) {

                foreach ($entry as $key => $val) {

                    if ($key === 'bibtexEntryType') {
                        $this->type = trim($val);
                    }
                    elseif ($key === 'bibtexCitation') {
                        $this->key = trim($val);
                    }
                    else {
                        $this->fields[$key] = trim($val);
                    }
                }
            }
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function hasField(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    public function hasNonEmptyField(string $key) : bool
    {
        return ($this->hasField($key) AND !empty($this->fields[$key]));
    }

    public function getField(string $key): ?string
    {
        return $this->fields[$key] ?? NULL;
    }

    public function setField(string $key, string $value, bool $firstPosition = false): void
    {
        if ($firstPosition) {
            $this->fields = array($key => $value) + $this->fields;
        }
        else {
            $this->fields[$key] = $value;
        }
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param string|null $channel
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

    public function getMissingFields(): array
    {
        return $this->missingFields;
    }

    public function hasMissingFields(): bool
    {
        return (count($this->missingFields) > 0);
    }

    public function setMissingField(string $field): void
    {
        $this->missingFields[] = $field;
    }

    public function hasCrossRef(): bool
    {
        return isset($this->fields['crossref']);
    }

    public function getCrossRefKey(): ?string
    {
        return $this->fields['crossref'] ?? NULL;
    }

    public function removeField(string $field): void
    {
        if (isset($this->fields[$field])) {
            unset($this->fields[$field]);
        }
    }

    public function renameField(string $oldFieldName, string $newFieldName): void
    {
        if (isset($this->fields[$oldFieldName])) {

            $oldVal = $this->fields[$oldFieldName];
            $this->removeField($oldFieldName);

            if ($oldVal !== '') {
                $this->fields[$newFieldName] = $oldVal;
            }
        }
    }

    private function writeLog(string $channel, string $msg, string $style = NULL): void
    {
        if (!isset($this->log[$channel])) {
            $this->log[$channel] = [];
        }

        $this->log[$channel][] = [ 'msg' => $msg, 'style' => $style ];
    }

    private static function getHttpResponseCode(array $httpResponseHeader): string
    {
        $match = [];
        preg_match('/ ([0-9]{3}) /', $httpResponseHeader[0], $match);

        return count($match) > 1 ? $match[1] : '';
    }

    /**
     * alternative constructor function to pull bibtex from the dblp server & set fields
     *
     * @param string $doi doi of entry whose information should be pulled
     * @param string $newKey key that will be assigned to the pulled entry
     * @return BibEntry
     * @throws Exception
     */
    public static function fromDblpByDoi(string $doi, string $newKey): static
    {
        $dblpUrl = self::DBLP_API_URL . $doi;

        try {
            return static::fromDblpByUrl($dblpUrl, $newKey);
        }
        catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * alternative constructor function to pull bibtex from the dblp server & set fields
     *
     * @param string $bibUrl biburl of entry whose information should be pulled
     * @param string $newKey key that will be assigned to the pulled entry
     * @return BibEntry
     * @throws Exception
     */
    public static function fromDblpByBibUrl(string $bibUrl, string $newKey): static
    {
        // revise biburl to use https instead of http
        $bibUrl = str_replace('http:', 'https:', $bibUrl);

        // revise biburl to revise standard bibtex entry and not the bibtex with crossref
        $bibUrl = str_replace('/bib/', '/bib1/', $bibUrl);

        try {
            return static::fromDblpByUrl($bibUrl, $newKey);
        }
        catch (Exception $ex) {
            throw $ex;
        }
    }


    /**
     * @param string $dblpUrl
     * @param string $newKey
     * @return BibEntry|null
     * @throws Exception
     */
    private static function fromDblpByUrl(string $dblpUrl, string $newKey): ?static
    {
        $log = [];

        // if the last request to dblp has been within the last X seconds, wait
        if (self::$lastDblpQuery - time() < self::DBLP_API_SLEEP_TIME) {
            sleep(self::DBLP_API_SLEEP_TIME);
        }

        self::$lastDblpQuery = time();

        if (str_contains($dblpUrl, '/bib1')) {
            $dblpUrl = str_replace('/bib1', '', $dblpUrl);
            $dblpUrl .= '.bib?param=1';
        }

        $log[] = 'Asking dblp for '. $dblpUrl;

        $dblpEntry = @file_get_contents($dblpUrl);

        $responseCode = self::getHttpResponseCode($http_response_header);

        $entry = NULL;

        if ($responseCode != '200' AND $responseCode != '303' AND $responseCode != '302') {

            throw new Exception("Entry with ". $dblpUrl ." not found in dblp!! Response code: " . $responseCode);
        }
        else {

            if ($dblpEntry === false) {

                throw new Exception("Unable to get content from dblp for ".$dblpUrl.": ".$dblpEntry);
            }
            else {

                $parseDblp = new ParseEntries();

                $parseDblp->setExpandMacro(true);
                $parseDblp->loadBibtexString($dblpEntry);
                $parseDblp->extractEntries();

                list(, , $entriesDblp, ) = $parseDblp->returnArrays();
                $logs = $parseDblp->getLog();
                $log = array_merge($log, $logs);

                if (count($entriesDblp) > 1) {
                    $log[] = "CAUTION: Retrieved > 1 entries for url ".$dblpUrl. ": " . count($entriesDblp);
                }

                foreach ($entriesDblp as $entryDblp) {

                    // TODO: check logic for more than 1 entry in response
                    $entry = new static($entryDblp);
                    $entry->setKey($newKey);

                    foreach ($log as $msg) {

                        if (str_contains($msg, 'CAUTION')) {
                            $entry->writeLog(self::LOG_OUTPUT, $msg, self::LOG_ALERT);
                        }
                        elseif (str_contains($msg, 'Asking dblp for')) {
                            $entry->writeLog(self::LOG_OUTPUT, $msg, self::LOG_EMPH);
                        }
                        else {
                            $entry->writeLog(self::LOG_OUTPUT, $msg);
                        }
                    }

                    // disable unneeded references
                    // Priority: DOI, eprint, URL
                    if ($entry->hasField('doi')) {

                        $entry->setField('doi', str_replace("\_" , "_", $entry->getField('doi')));

                        if ($entry->hasField('url')) {
                            $entry->renameField('url', '_url');
                        }

                        if ($entry->hasField('eprint')) {
                            $entry->renameField('eprint', '_eprint');
                        }

                        break;
                    }
                    elseif ($entry->hasField('eprint')) {

                        if ($entry->hasField('url')) {
                            $entry->renameField('url', '_url');
                        }

                        break;
                    }
                    elseif ($entry->hasField('url')) {

                        $entry->setField('url', str_replace("\_" , "_", $entry->getField('url')));

                        break;
                    }
                }
            }
        }

        return $entry;
    }

    /**
     * @param string $query
     * @return BibEntry|null
     */
    public static function fromDblpByTitle(string $query): ?static
    {
        $log = [];
        $entry = NULL;

        // if the last request to dblp has been within the last X seconds, wait
        if (self::$lastDblpSearchQuery - time() < self::DBLP_API_SLEEP_TIME){
            sleep(self::DBLP_API_SLEEP_TIME);
        }

        self::$lastDblpQuery = time();

        $dblpUrl = self::DBLP_SEARCH_API_URL .'?q='. urlencode(trim($query)) .'&format=json';

        $request = @file_get_contents($dblpUrl);

        $response = json_decode($request, true);
        $result = $response['result'];

        // var_dump($request);
        // echo'<hr><hr>';

        $entries = [];

        if ($result['hits']['@total'] !== '0') {

            $hits = $result['hits']['hit'];

            foreach ($hits as $hit) {

                if (!empty($hit['info']['authors'])) {

                    $authors = $hit['info']['authors'];
                    unset($hit['info']['authors']);
                    $formattedAuthors = [];

                    foreach ($authors['author'] as $authorArr) {
                        foreach ($authorArr as $key => $val) {
                            if ($key === 'text') {
                                $formattedAuthors[] = $val;
                            }
                        }
                    }
                }

                $entry = new static($hit['info']);

                if (!empty($formattedAuthors)) {
                    $entry->setField('author', implode(' and ', $formattedAuthors));
                }

                $entries[] = $entry;
            }
        }

        if (count($entries) > 1) {
            $entry = $entries[0];
            $entry->writeLog(self::LOG_OUTPUT, "CAUTION: Retrieved > 1 entries for query ". $query .": ". count($entries) .". Returning the first one.", self::LOG_ALERT);
        }

        if (count($entries) === 0) {
            return NULL;
        }

        return $entry;
    }

    // TODO: fields-parameter to check partially

    public function diff(BibEntry $otherBibEntry): void
    {
        $count = 0;

        // first revise pages, month etc.
        $this->reviseFields();
        $otherBibEntry->reviseFields();

        // then check for differences
        foreach ($this->fields as $key => $val) {

            // case-insensitive string-comparison
            if ($key !== '_order' AND strcasecmp($this->fields[$key], $otherBibEntry->getField($key))) {

                $count ++;
                $msg = "CAUTION: Difference detected in '". $key .": '". $this->fields[$key] ."' VS '". $otherBibEntry->getField($key) ."'";
                $this->writeLog(self::LOG_OUTPUT, $msg);
                //echo $msg .'<br>';
            }
        }

        $msg = "Diff-Summary: detected ". $count ." differences";
        $this->writeLog(self::LOG_OUTPUT, $msg);
    }

    /**
     * @param BibEntry $otherBibEntry
     * @param string[]|null $fields
     * @return boolean
     *
     * Merges otherBibEntry's fields into current entry, if
     *  1) $fields === NULL and field is part of $this->missingFields
     * OR
     *  2) $fields !== NULL and field is part of $fields
     */
    public function merge(BibEntry $otherBibEntry, array $fields = NULL): bool
    {
        $edited = false;

        if ($fields === NULL) {
            $fields = $this->missingFields;
        }

        foreach ($otherBibEntry->getFields() as $key => $value) {

            if (!empty($fields) AND in_array($key, $fields)) {

                $this->fields[$key] = trim($value);
                $edited = true;
                $this->writeLog(self::LOG_CHANGES, "Added field '". $key ."' in entry ". $this->key ." from current dblp entry");
            }
        }

        return $edited;
    }

    /**
     * @param null|string[] $crossRefEntry
     */
    public function setMissingMandatoryFields(array $crossRefEntry = NULL): void
    {
        if (!$this->hasNonEmptyField('doi')) {
            $this->setMissingField('doi');

            if (!$this->hasNonEmptyField('eprint')) {
                $this->setMissingField('eprint');

                if (!$this->hasNonEmptyField('url')) {
                    $this->setMissingField('url');
                }
            }
        }

        if (isset($this->type)) {

            if (array_key_exists($this->type, self::MANDATORY_BIBTEX_FIELDS)) {

                foreach (self::MANDATORY_BIBTEX_FIELDS[$this->type] as $field) {

                    if (!isset($this->fields[$field])) {

                        if (isset($crossRefEntry) AND isset($crossRefEntry[$field])) {
                            $msg = "CROSSREF: Field '$field' mandatory for type '$this->type' missing in bibtex record with key '" . $this->key . "' but found in cross-referenced record with key '" . $crossRefEntry['bibtexCitation'];
                            $this->writeLog(self::LOG_CHANGES, $msg);
                        }
                        else {
                            $msg = "CAUTION: Field '$field' mandatory for type '$this->type' missing in bibtex record with key '" . $this->key;
                            $this->writeLog(self::LOG_CHANGES, $msg, self::LOG_ALERT);

                            $this->missingFields[] = $field;
                        }
                    }
                    elseif (strlen($this->fields[$field]) == 0) {
                        $msg = "CAUTION: Field '$field' mandatory for type '$this->type' is empty in bibtex record with key '". $this->key."'";
                        $this->writeLog(self::LOG_CHANGES, $msg, self::LOG_ALERT);

                        $this->missingFields[] = $field;
                    }
                }

                if ($this->type === 'book' OR $this->type === 'inbook') {

                    if (!isset($this->fields['author']) AND !isset($this->fields['editor'])) {

                        $msg = "CAUTION: Field 'author' or 'editor'  mandatory for type '$this->type' missing in bibtex record with key '". $this->key;
                        $this->writeLog(self::LOG_CHANGES, $msg, self::LOG_ALERT);

                        $this->missingFields[] = 'author';
                        $this->missingFields[] = 'editor';
                    }
                    elseif (isset($this->fields['author']) AND isset($this->fields['editor'])) {

                        $msg = "CAUTION: Only field 'author' or 'editor'  can be used for type '$this->type' missing in bibtex record with key '". $this->key;
                        $this->writeLog(self::LOG_CHANGES, $msg, self::LOG_ALERT);

                        //$this->redundantFields[] = 'author';
                        $this->redundantFields[] = 'editor';
                    }
                }
            }
            else {
                $msg = "CAUTION: Undefined type '$this->type' of bibtex record with key ". $this->key;
                $this->writeLog(self::LOG_CHANGES, $msg, self::LOG_ALERT);
            }
        }
    }

    public static function cleanupDoi(string $doi): string
    {
        $doi = str_replace("\_" , '_', $doi);

        foreach (self::DOI_PREFIXES AS $doiKey => $prefix) {
            $doi = str_replace($prefix, '', $doi);
        }

        return $doi;
    }

    /**
     * @param bool $disableAllReferences if true, sets every reference-'key' to '_key'; if false, only disables under certain conditions
     */
    public function cleanupElectronicReferences(bool $disableAllReferences = false): void
    {
        if (!empty($this->fields['doi'])) {

            $doi = self::cleanupDoi($this->fields['doi']);

            if (strcmp($doi, $this->fields['doi']) !== 0) {
                $this->writeLog(self::LOG_CHANGES, "Revised DOI for entry $this->key");
            }

            if ($disableAllReferences) {
                $this->fields['_doi'] = $doi;
                $this->writeLog(self::LOG_CHANGES, "Disabled DOI for entry $this->key");
            }
            else {
                $this->fields['doi'] = $doi;
            }
        }

        if (!empty($this->fields['ee']) AND !$this->hasField('url')) {

            $this->renameField('ee', 'url');
            $this->writeLog(self::LOG_CHANGES, "Changed EE to URL for entry $this->key");
        }

        if ($this->hasField('note') AND StringHelper::startsWith(trim($this->fields['note']), "\url{" )) {

            $note = trim($this->fields['note']);

            // if \note{\url{...}} -> extract url from note field to url field (if not yet existing)
            if ($this->hasField('eprint')) {

                $this->renameField('note', '_note');
                $msg = "Disabled note containing URL as EPRINT field already used for entry $this->key";
                $this->writeLog(self::LOG_CHANGES, $msg);
            }
            elseif ($this->hasNonEmptyField('doi')) {

                $this->renameField('note', '_note');
                $msg = "Disabled note containing URL as DOI field already used for entry $this->key";
                $this->writeLog(self::LOG_CHANGES, $msg);
            }
            elseif ($this->hasField('url')) {

                $this->renameField('note', '_note');
                $msg = "Disabled note containing URL as URL field already used for entry $this->key";
                $this->writeLog(self::LOG_CHANGES, $msg);
            }
            else {
                $url = substr($note, 5, strpos($note,"}") - 5);
                $this->fields['_note'] = $note;
                $newNote = substr($note, strpos($note,"}") + 1);

                if (strlen($newNote) === 0) {
                    unset($this->fields['note']);
                }
                else {
                    $this->fields['note'] = $newNote;
                }

                $this->fields['url'] = $url;
                $msg = "Extracted URL from note field and added to URL field for entry $this->key";
                $this->writeLog(self::LOG_CHANGES, $msg);
            }
        }

        if (!empty($this->fields['url'])) {

            $url = $this->fields['url'];

            $url = str_replace("\_" , "_", $url);

            if ($disableAllReferences) {

                $this->setField('url', $url);
                $this->renameField('url', '_url');
                $this->writeLog(self::LOG_CHANGES, "Disabled URL for entry $this->key");
            }
            else {

                if ($this->hasField('doi') AND $this->fields['doi'] !== "") {

                    $this->setField('url', $url);
                    $this->renameField('url', '_url');
                    $this->writeLog(self::LOG_CHANGES, "Disabled URL as DOI available for entry $this->key");
                }
                else {

                    foreach (self::DOI_PREFIXES as $doikey => $prefix) {

                        if (strpos($url, $prefix) !== false) {

                            $this->setField('url', $url);
                            $this->renameField('url', '_url');
                            $this->fields['doi'] = str_replace($prefix, "", $url);
                            $this->writeLog(self::LOG_CHANGES, "Revised DOI and disabled URL for entry $this->key");
                        }
                    }
                }

                if (!empty($this->fields['eprint'])) {

                    $this->renameField('url', '_url');
                    $this->writeLog(self::LOG_CHANGES, "Disabled URL as EPRINT available for entry $this->key");
                }
            }
        }

        if (!empty($this->fields['eprint'])) {

            if ($disableAllReferences) {

                $this->renameField('eprint', '_eprint');
                $this->writeLog(self::LOG_CHANGES, "Disabled EPRINT for entry $this->key");
            }
            else {

                if (!empty($this->fields['doi'])) {

                    $this->renameField('eprint', '_eprint');
                    $this->writeLog(self::LOG_CHANGES, "Disabled EPRINT as DOI available for entry $this->key");
                }
            }
        }
    }

    /**
     * @param bool $debug
     */
    public function cleanArxivReferences(bool $debug = true): void
    {
        if ($this->hasField('note') AND StringHelper::startsWith(trim($this->fields['note']), "\url{" )) {

            // if \note{\url{..arxiv..}} -> extract arxiv information to eprint field (if not yet existing)
            if (stripos($this->fields['note'], 'arXiv') !== false) {

                $note = trim($this->fields['note']);

                if ($this->hasField('eprint')) {

                    if ($debug) {
                        //$this->renameField('note', '-note');
                    }
                    else {
                        $this->renameField('note', '_note');
                    }

                    $msg = "Disabled note containing URL with ARXIV as EPRINT field already used for entry $this->key";
                    $this->writeLog(self::LOG_CHANGES, $msg);
                }
                elseif ($this->hasNonEmptyField('doi')) {

                    if ($debug) {
                        //$this->renameField('note', '-note');
                    }
                    else {
                        $this->renameField('note', '_note');
                    }

                    $msg = "Disabled note containing URL with ARXIV as DOI field already used for entry $this->key";
                    $this->writeLog(self::LOG_CHANGES, $msg);
                }
                else {

                    $url = substr($note, 5, strpos($note,"}") - 5);

                    if ($debug) {
                        //$this->renameField('note', '-note');
                        $this->fields['+eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $url, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                    }
                    else {
                        $this->renameField('note', '_note');
                        $this->fields['eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $url, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                    }

                    $msg = "Extracted ARXIV from NOTE with URL and added to EPRINT field for entry $this->key";
                    $this->writeLog(self::LOG_CHANGES, $msg);
                }
            }
        }

        // if \url{...arXiv...} -> extract arXiv information to eprint field (if not yet set)
        if ($this->hasNonEmptyField('url')
            AND !$this->hasField('doi')
            AND stripos($this->fields['url'], 'arXiv') !== false
            AND (!$this->hasField('eprint') OR empty($this->fields['eprint']))) {

            $url = $this->fields['url'];

            if ($debug) {
                $this->fields[self::DEBUG_MARK.'eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $url, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                //$this->renameField('url', '-url');
            }
            else {
                $this->fields['eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $url, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                $this->renameField('url', '_url');
            }

            $this->writeLog(self::LOG_CHANGES, "Extracted ARXIV from URL and added to EPRINT field for entry $this->key");
        }

        // if \journal{arXiv preprint arXiv:(arxiv-id)} extract (arxiv-id) to eprint (if not yet existing)
        if ($this->hasNonEmptyField('journal') AND stripos($this->fields['journal'], 'arXiv preprint arXiv:') !== false) {

            $journal = $this->fields['journal'];

            if ($debug) {
                $this->fields[self::DEBUG_MARK.'journal'] = trim(preg_replace('/((?i)arxiv(?-i):[a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/', '', $journal));
            }
            else {
                $this->fields['journal'] = trim(preg_replace('/((?i)arxiv(?-i):[a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/', '', $journal));
            }


            if (!empty($this->fields['eprint'])) {

                if ($debug) {
                    $this->fields[self::DEBUG_MARK.'eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $journal, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                }
                else {
                    $this->fields['_eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $journal, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                }

                $this->writeLog(self::LOG_CHANGES, "Extracted ARXIV id from journal to _eprint field as eprint field already exists for entry $this->key");
            }
            else {

                if ($debug) {
                    $this->fields[self::DEBUG_MARK.'eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $journal, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                }
                else {
                    $this->fields['eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $journal, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                }

                $this->writeLog(self::LOG_CHANGES, "Extracted ARXIV id from journal to eprint for entry $this->key");
            }
        }
        // if \journal{arXiv ..} (or \journal{corr}) and \volume{(arxiv-id)} extract (arxiv-id) to _eprint (if not yet existing)
        elseif ($this->hasNonEmptyField('journal')
            AND (stripos($this->fields['journal'], 'arXiv') !== false OR stripos($this->fields['journal'], 'CoRR') !== false)
            AND $this->hasNonEmptyField('volume')) {

            if (!$this->hasField('eprint') AND empty($this->fields['_eprint'])) {

                $volume = $this->fields['volume'];
                $journal = $this->fields['journal'];
                $newJournal = preg_replace('/((?i)arxiv(?-i):[a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/', '', $journal);

                if ($debug) {

                    $this->fields[self::DEBUG_MARK.'eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $volume, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                    $this->writeLog(self::LOG_CHANGES, "Extracted ARXIV id from journal & volume to eprint field for entry $this->key");

                    if ($journal !== $newJournal) {
                        $this->fields[self::DEBUG_MARK.'journal'] = $newJournal;
                        $this->writeLog(self::LOG_CHANGES, "Updated journal field for entry $this->key");
                    }
                }
                else {

                    $this->fields['eprint'] = preg_match('/([\d.]+[^a-zA-Z\s}])/', $volume, $matches) ? $matches[0] : ''; // old regex: '/([a-zA-Z\-\/]*[\d.]+[^a-zA-Z\s}])/'
                    $this->writeLog(self::LOG_CHANGES, "Extracted ARXIV id from journal & volume to eprint field for entry $this->key");

                    if ($journal !== $newJournal) {
                        $this->renameField('journal', '_journal');
                        $this->fields['journal'] = $newJournal;
                        $this->writeLog(self::LOG_CHANGES, "Updated journal field for entry $this->key");
                    }

                    $this->renameField('volume', '_volume');
                    $this->writeLog(self::LOG_CHANGES, "Renamed volume to _volume for entry $this->key");
                }
            }
        }
    }

    public function getBibTexString(): string
    {
        $output = '';

        if (!empty($this->type)) {

            $output .= "\n@" . $this->type . "{";

            if (!empty($this->key)) {

                $output .= $this->key . ",\n";
            }
            else {

                $msg = "CAUTION: no key set for entry: ";
                ob_start();
                var_dump($this->fields);
                $msg .= ob_get_clean();

                $this->writeLog(self::LOG_OUTPUT, $msg, self::LOG_ALERT);
                throw new Exception($msg);
            }
        }
        else {

            $msg = "CAUTION: no type set for entry:";
            ob_start();
            var_dump($this->fields);
            $msg .= ob_get_clean();

            $this->writeLog(self::LOG_OUTPUT, $msg, self::LOG_ALERT);
            throw new Exception($msg);
        }

        foreach ($this->fields as $key => $value) {

            if ($key === 'month' AND !empty($value) AND array_key_exists($value, self::MONTH_MAPPING)) {
                $output .= "  " . $key . " = " . $value . ",\n";
            }
            else {
                $output .= "  " . $key . " = {" . $value . "},\n";
            }

            if (str_contains($key, "%")) {
                $this->writeLog(self::LOG_CHANGES, "CAUTION: Found '%' in some key of entry" . $this->key, self::LOG_ALERT);
            }
        }

        $output .= "}\n";

        return $output;
    }

    /**
     * @param string[]|null $fields
     *
     * Revises fields: pages, title, month, and journal
     */
    public function reviseFields(array $fields = NULL): void
    {
        // revise pages
        if ($this->hasField('pages') AND (($fields !== NULL AND in_array('pages', $fields)) OR $fields === NULL)) {

            $pages = $this->getField('pages');

            $pages = str_replace('‒', '-', $pages); // different UTF8 dashes
            $pages = str_replace('–', '-', $pages); // different UTF8 dashes
            $pages = str_replace('—', '-', $pages); // different UTF8 dashes
            $pages = str_replace('―', '-', $pages); // different UTF8 dashes

            $pages = str_replace('-', '--', str_replace('--', '-', $pages));
            $pages = str_replace(' -- ', '--', $pages);
            $pages = str_replace(' --', '--', $pages);
            $pages = str_replace('-- ', '--', $pages);

            if (strcmp($pages, $this->getField('pages')) !== 0) {
                $this->writeLog(self::LOG_CHANGES, "Revised pages for entry $this->key");
            }

            $this->setField('pages', $pages);
        }

        // revise title
        if ($this->hasField('title') AND (($fields !== NULL AND in_array('title', $fields)) OR $fields === NULL)) {

            $title = $this->getField('title');

            if (str_ends_with($title, '.')) {

                $this->setField('title', substr($title, 0, strlen($title)-1));
                $this->writeLog(self::LOG_CHANGES, "Revised title for entry $this->key");
            }
        }

        // revise month
        if ($this->hasField('month') AND (($fields !== NULL AND in_array('month', $fields)) OR $fields === NULL)) {

            $month = strtolower($this->getField('month'));

            if (StringHelper::endsWith( $month, ',') OR StringHelper::endsWith($month,'.')) {
                $month = substr($month,0, strlen($month) - 1);
            }

            $found = false;

            foreach (self::MONTH_MAPPING as $mon => $abbreviations) {

                if (in_array($month, $abbreviations)) {

                    $this->setField('month', $mon);
                    $this->writeLog(self::LOG_CHANGES, "Revised month for entry $this->key");
                    $found = true;
                }
            }

            if (!$found) {

                foreach(self::MONTH_NAMES as $short=>$expanded) {
                    $month = str_replace($short . '.', $expanded, $month);
                    $month = preg_replace('/(\b)' . $short . '(\b)/', '$1' . $expanded . '$2', $month);
                }

                $msg = 'CAUTION: Check content of month {'. $month .'} for entry ' .$this->key;
                $this->setField('month', $month);
                $this->writeLog(self::LOG_CHANGES, $msg, self::LOG_ALERT);
            }
        }

        // revise journal
        if ($this->hasField('journal') AND (($fields !== NULL AND in_array('journal', $fields)) OR $fields === NULL)) {

            $journal = trim($this->getField('journal'));

            if (isset(self::JOURNAL_ABBREVIATIONS[$journal])) {
                $this->setField('journal', self::JOURNAL_ABBREVIATIONS[$journal]);
                $this->writeLog(self::LOG_CHANGES, 'Revised journal for entry '. $this->key .' from "'. $journal .'" to "'. self::JOURNAL_ABBREVIATIONS[$journal]);
            }
        }
    }
}
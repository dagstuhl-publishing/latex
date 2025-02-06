<?php

namespace Dagstuhl\Latex\Validation;

use Dagstuhl\Latex\Bibliography\Bibliography;
use Dagstuhl\Latex\Compiler\LogParser\DefaultLatexLogParser;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Metadata\MetadataItem;
use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Styles\Packages;
use Dagstuhl\Latex\Utilities\Filesystem;
use stdClass;

class LatexValidator
{
    const MSG_TYPE_UPLOAD = 'msg-on-upload';
    const MSG_TYPE_CHANGELOG = 'msg-changelog';

    protected LatexFile $latexFile;

    private ?array $metadataSyntaxErrors = NULL;

    /**
     * LatexValidator constructor.
     */
    public function __construct(LatexFile $latexFile)
    {
        $this->latexFile = $latexFile;
    }

    public function getLatexFile(): LatexFile
    {
        return $this->latexFile;
    }

    public function getUploadErrors(stdClass $metadata = NULL): array
    {
        return array_merge(
            $this->getBibErrors(),
            $this->getPackageMessages(Packages::CLASSIFIED_FORBIDDEN, self::MSG_TYPE_UPLOAD),
            $this->getMetadataErrors($metadata),
            $this->getIfErrors()
        );
    }

    public function getUploadWarnings(stdClass $metadata = NULL): array
    {
        return array_merge(
            $this->getBibWarnings(),
            $this->getPackageMessages(Packages::CLASSIFIED_WARNING_ON_UPLOAD, self::MSG_TYPE_UPLOAD),
            $this->getMetadataWarnings($metadata)
        );
    }

    // -------- metadata macros and environments --------

    /**
     * @return string[]
     */
    public function getMetadataErrors(stdClass $metadata = NULL): array
    {
        return array_merge(
            $this->getMetadataSyntaxErrors(),
            $this->getMetadataContentErrors($metadata)
        );
    }

    /**
     * @return string[]
     */
    public function getMetadataSyntaxErrors(): array
    {
        if ($this->metadataSyntaxErrors !== NULL) {
            return $this->metadataSyntaxErrors;
        }

        $result = [];

        $style = $this->latexFile->getStyle();

        $uniqueMandatoryMacros = $style->getMetadataItems([
            MetadataItem::GROUP_MANDATORY,
            MetadataItem::GROUP_MACROS,
            MetadataItem::GROUP_AT_MOST_1
        ] );

        foreach($uniqueMandatoryMacros as $item) {

            $latexIdentifier = $item->getLatexIdentifier();

            $macros = $this->latexFile->getMacros($latexIdentifier);
            $count = count($macros);

            if ($count === 0) {
                $result[] = 'ERROR: mandatory macro <code>\\'.$latexIdentifier.'</code> not used, please add.';
            }
            elseif ($count > 1) {
                $result[] = 'ERROR: macro <code>\\'.$latexIdentifier.'</code> used '.$count.' times, but only allowed once. '.
                    'Please revise your source!';
            }
        }

        $optionalMacros = $style->getMetadataItems([
            MetadataItem::GROUP_OPTIONAL,
            MetadataItem::GROUP_MACROS
        ]);

        foreach($optionalMacros as $item) {

            $latexIdentifier = $item->getLatexIdentifier();

            $macros = $this->latexFile->getMacros($latexIdentifier);
            $count = count($macros);

            if ($count > 1 AND !$item->belongsTo([MetadataItem::GROUP_MULTI])) {
                $result[] = 'ERROR: optional macro <code>\\'.$latexIdentifier.'</code> used '.$count.' times, but allowed at most once. '.
                    'Please revise your source.';
            }
        }

        $repeatableMandatoryMacros = $style->getMetadataItems([
            MetadataItem::GROUP_MANDATORY,
            MetadataItem::GROUP_MACROS,
            MetadataItem::GROUP_MULTI
        ] );

        foreach($repeatableMandatoryMacros as $item) {

            $latexIdentifier = $item->getLatexIdentifier();

            $macros = $this->latexFile->getMacros($latexIdentifier);

            if (count($macros) === 0) {
                $result[] = 'ERROR: mandatory macro <code>\\'.$latexIdentifier.'</code> not used, please add at least once.';
            }
        }

        $mandatoryEnvironments = $style->getMetadataItems([
            MetadataItem::GROUP_MANDATORY,
            MetadataItem::GROUP_ENVIRONMENTS
        ]);

        foreach($mandatoryEnvironments as $item) {

            $latexIdentifier = $item->getLatexIdentifier();

            $environments = $this->latexFile->getEnvironments($item->getLatexIdentifier());

            $count = count($environments);

            if ($count === 0) {
                $result[] = 'ERROR: mandatory environment <code>\\begin{'.$latexIdentifier.'}...\\end{'.$latexIdentifier.'}</code> '.
                    'not used, please add at least once.';
            }
            elseif ($count > 1) {
                $result[] = 'ERROR: mandatory environment <code>\\begin{'.$latexIdentifier.'}...\\end{'.$latexIdentifier.'}</code> '.
                    'used '.$count.' times but only allowed once. Please revise your source.';
            }
        }

        if (count($result) > 0 AND count($this->latexFile->getIfs()) > 0) {
            $result[] = 'Maybe we cannot parse the macros properly since you used <code>\\if</code>-directives. '.
                'Please remove them and re-upload your source files.';
        }

        // add warnings from log of condensed file, if other warnings are present

        if (count($result) > 0) {

            $fileName = $this->latexFile->getFilename();
            if (strpos($fileName, LatexFile::EXTENSION_CONDENSED_FILE) !== false) {

                try {
                    $log = Filesystem::get($this->latexFile->getPath('log'));
                }
                catch (\Exception $ex) {
                    $log = '';
                }

                $lines = explode("\n", $log);

                foreach($lines as $line) {
                    preg_match('/(NOTE|WARNING|ERROR):.*/', $line, $match);

                    if (isset($match[0])) {
                        $result[] = str_replace("\n", '', $match[0]);
                    }
                }
            }
        }

        $this->metadataSyntaxErrors = $result;

        return $result;
    }

    public function getMetadataContentErrors(stdClass $metadata = NULL): array
    {

        if (count($this->getMetadataSyntaxErrors()) > 0 OR $metadata === NULL) {
            return [];
        }

        $errors = [];

        $authors = $metadata->authors;

        if (is_array($authors)) {

            foreach($authors as $author) {
                if (StringHelper::containsAnyOf($author->name, [ ';', ',', ' and ', '\\and '])) {
                    $errors[] = 'ERROR: <b>Shared author macro</b> detected ('.$author->name.'). Please use one <code>\author</code> macro per author.';
                }

                $email = trim($author->email);

                if (strlen($email) > 0) {
                    $email = str_replace('\_', '_', $email);

                    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $errors[] = 'ERROR: <b>Invalid email address</b> (' . $email . ') detected in <code>\author</code> macro, please correct.';
                    }

                    if (StringHelper::containsAnyOf($email, [ ';', ',', '{', '}' ])) {
                        $errors[] = 'ERROR: <b>Shared email address</b> (' . $email . ') detected in author macro. Please use one <code>\author</code> macro per author, and only one email per author.';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     *
     * to be overridden in concrete application
     */
    public function getMetadataWarnings(stdClass $metadata = NULL): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getIfErrors(): array
    {
        $ifs = $this->latexFile->getIfs();

        $occurrences = [];

        foreach($ifs as $if) {
            $occurrences[] = $if['name'];
        }

        $count = count($occurrences);

        $names = array_unique($occurrences);
        $namesCount = count($names);

        if ($count > 10 OR $namesCount > 2) {
            return [
                'ERROR: You make heavy use of <code>\if</code>-constructions. '.implode(',', $occurrences).
                'That makes it very difficult for us to edit your source code during the typesetting process. '.
                'Please remove the <code>\if</code>-constructions and upload your source again.'
            ];
        }

        return [];
    }

    // --------- bibliography ---------------------------

    /**
     * @return string[]
     */
    public function getBibErrors(): array
    {
        $bibErrors = [];

        $logParser = new DefaultLatexLogParser($this->latexFile);
        $bibLog = implode(' ', $logParser->getBibTexLog());
        if (str_contains($bibLog, 'I found no \bibstyle command')) {
            $bibErrors[] = 'ERROR: missing <code>\bibliogrphystyle</code> macro -> please add <code>\bibliographystyle{plainurl}</code> in your main LaTeX file';
        }

        if (!str_contains($bibLog, 'I couldn\'t open database file')) {
            return $bibErrors;
        }

        $bibFiles = $this->latexFile->getBibliography()->getPathsToUsedBibFiles();

        $missingFiles = [];
        foreach($bibFiles as $file) {
            if (!Filesystem::fileExists($file)) {
                $missingFiles[] = basename($file);
            }
        }

        if (count($missingFiles) > 0) {
            $bibErrors[] = 'ERROR: Missing bib-file(s): '.implode(', ',$missingFiles);
        }

        return $bibErrors;
    }

    /**
     * @return string[]
     */
    public function getBibWarnings(): array
    {
        $bibWarnings = [];

        $bibType = $this->latexFile->getBibliography()->getType();

        if ($bibType === Bibliography::TYPE_INLINE) {
            $bibWarnings[] = 'WARNING: <b>Inline bibliography</b> found. - Please use a <b>bib-file instead</b> and include it via <code>\bibliography{...}</code>.';
            $bibWarnings[] = 'This would allow us to extract the references and attach them to the metadata that we pass on to our DOI-service provider.';
            $bibWarnings[] = 'Unfortunately, we cannot offer this service for inline-bibliographies.';
        }

        return $bibWarnings;
    }

    // --------- packages -------------------------------

    /**
     * @param string $packageClassification (see the Packages::class for package classifications)
     * @param string $msgType
     * @return string[]
     *
     * to be overridden in concrete application
     */
    public function getPackageMessages(string $packageClassification, string $msgType): array
    {
        return [];
    }

    public function getPackageString() : string
    {
        $packages = $this->latexFile->getUsedPackages($options);

        $packageString = [];

        foreach($packages as $key=>$package) {
            $name = trim($package);
            $opt = implode(',',$options[$key]);

            $packageString[] = $name.'['.$opt.']';
        }

        return implode(';', $packageString);
    }


}
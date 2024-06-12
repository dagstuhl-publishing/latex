<?php

namespace Dagstuhl\Latex\Metadata;

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Strings\Converter;
use Dagstuhl\Latex\Strings\MetadataString;
use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Utilities\Filesystem;

abstract class MetadataMappings
{
    const PATTERNS_FULL_VERSION = [
        '\\\\relatedversion{',
        'full paper', 'extended paper', 'long paper',
        'extended version', 'full version', 'long version',
        'full proof', 'complete proof'
    ];

    const FULL_VERSION_SNIPPETS_EXAMPLE_FILES = [
        '\relatedversion{}',
        '\verb+\relatedversion+',
        '\relatedversion{A full version of the paper is available at \url{...}.}',
        '\item If you refer to a longer version of the paper (``full version\'\')'
    ];

    /** ---- utf8 - metadata maps ---- */

    public static function reviseMacroV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $metadataString = new MetadataString($string, $latexFile);

        if ($metadataString->containsMacros()) {
            $metadataString->expandMacros();
            $metadataString->normalizeMacro();
        }
        else {
            $metadataString->normalizeSimpleMacro();
        }

        return $metadataString->trim()->getString();
    }

    public static function reviseTitleV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $title = self::reviseMacroV1($string, $originalString, $latexFile);

        $subtitle = $latexFile->getMacro('subtitle');

        if ($subtitle !== NULL) {
            $subtitle = trim($subtitle->getArgument());

            if ($subtitle !== '') {
                preg_match('/\\\\def\\\\subtitleseperator{(.*)}/U', $latexFile->getContents(), $matches);
                $separator = $matches[1] ?? ': ';

                $title = $title . $separator . $subtitle;
                $title = self::reviseMacroV1($title, $title, $latexFile);
            }
        }

        return $title;
    }

    public static function reviseAbstractV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $metadataString = new MetadataString($string, $latexFile);

        $metadataString->expandMacros();

        if ($metadataString->containsMacros()) {
            $metadataString->expandMacros();

            // echo $metadataString->getString();
            // exit();
        }

        $metadataString->normalizeAbstract();

        return $metadataString->trim()->getString();
    }

    public static function reviseAuthorV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $metadataString = new MetadataString($string, $latexFile);

        if ($metadataString->containsMacros()) {
            $metadataString->removeTextSuperscript()
                ->expandMacros()
                ->normalizeMacro();
        }
        else {
            $metadataString->normalizeSimpleMacro();
        }

        $metadataString->removeCustomFootnoteMarks();

        return $metadataString->trim()->getString();
    }

    /**
     * @return string[] with keys affiliation and homepageUrl
     */
    public static function reviseAffiliationV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): array
    {
        $metadataString = new MetadataString($string, $latexFile);

        return $metadataString->convertAffiliation();
    }

    /**
     * @return string|string[]
     */
    public static function reviseEmailV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string|array
    {
        $string = trim($string);
        $string = preg_replace('/^\[/', '', $string);
        $string = preg_replace('/\]$/', '', $string);
        $string = trim($string);

        return str_replace('\_', '_', $string);
    }

    public static function reviseFundingV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $latexString = new MetadataString($string);

        return $latexString->removeMiniPages()
            ->removeFlags()
            ->normalizeMacro()
            ->getString();
    }

    public static function trim(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        return trim($string);
    }

    public static function removeBraces(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $string = str_replace('{', '', $string);
        return str_replace('}', '', $string);
    }

    public static function calculateCategoryV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $categories = $latexFile->getMacros('category');

        $count = count($categories);

        if ($count === 0) {
            return 'Regular Paper';
        }

        return trim($string);
    }

    public static function calculateDartsCategoryV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        return 'Artifact';
    }

    public static function calculatePagesFromPdfV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): int
    {
        return Filesystem::exists($latexFile->getPath('pdf'))
            ? $latexFile->getNumberOfPages()
            : 0;
    }

    /**
     * @return string[]
     */
    public static function calculateFullVersionReferencesV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): array
    {
        $pattern = '/('.implode('|', self::PATTERNS_FULL_VERSION).')/i';
        $snippets = StringHelper::extract($pattern, $latexFile->getContents(), 200, false);

        foreach($snippets as $key=>$snippet) {

            $match = false;
            foreach(self::FULL_VERSION_SNIPPETS_EXAMPLE_FILES as $example) {
                if (str_contains($snippet, $example)) {
                    $match = true;
                }
            }

            if ($match) {
                unset($snippets[$key]);
            }
        }

        $snippets = StringHelper::emphasize($pattern, $snippets);

        $references = [];

        $macro = NULL;
        foreach($snippets as $key=>$snippet) {
            if (str_contains($snippet, '\relatedversion{'))  {
                $macro = $snippet;
            }
            else {
                $references[] = $snippet;
            }
        }

        // remove the macro itself at the end of the list
        if ($macro !== NULL) {
            $references[] = $macro;
        }

        foreach($references as $key=>$reference) {
            $references[$key] = preg_replace('/\n+/sm', "\n", $reference);
        }

        return array_values($references);
    }

    public static function calculateArticleNoFrontmatter(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): int
    {
        return 0;
    }

    /** latex-normalization of metadata */

    public static function normalizeLatexV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
       return Converter::normalizeLatex($string);
    }

    public static function normalizeNameListV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $string = StringHelper::replaceLinebreakByBlank($string);
        $string = Converter::normalizeLatex($string);
        $string = str_replace('\{', '{', $string);

        $string = str_replace('\}', '}', $string);

        return trim($string);
    }

    public static function normalizeLineBreaksAndBlanksV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $string = StringHelper::removeCarriageReturns($string);
        $string = StringHelper::replaceLinebreakByBlank($string);

        $string = StringHelper::replaceMultipleWhitespacesByOneBlank($string);

        return trim($string);
    }

    public static function normalizeNameV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $footnotePlaceholder = 'XXXfootnoteXXX';
        $urlPlaceholder = 'XXXurlXXX';
        $andPlaceholder = 'XXXandXXX';

        // author fields may contain references to footnotes modelled as, e.g., $^2$
        // prevent normalization by substituting a placeholder for them
        $footnoteReference = false;

        $match = [];
        preg_match('/\$.*\$/U', $string, $match);

        if (count($match) === 1) {
            $footnoteReference = $match[0];
            $string = preg_replace('/\$.*\$/U', $footnotePlaceholder, $string);
        }

        $url = false;
        $and = false;

        $match = [];
        preg_match('/\\\\url{.*}/U', $string, $match);

        if (count($match) === 1) {
            $url = $match[0];
            $string = preg_replace('/\\\\url{.*}/U', $urlPlaceholder, $string);
        }

        $match = [];
        preg_match('/\\\\and /', $string, $match);

        if (count($match) !== 0) {
            $and = true;
            $string = preg_replace('/\\\\and /U', $andPlaceholder, $string);
        }

        $string = Converter::normalizeLatex($string);

        // Curly braces should normally not occur in author's name, affiliation, email, orcid and funding,
        // so delete. In case of a change, a comment is written into the tex-file.
        // In case of pdf changes, the typesetting step fails.
        $string = str_replace('\{', '{', $string);
        $string = str_replace('\}', '}', $string);
        $string = str_replace('\backslash', '\\', $string);

        if ($footnoteReference !== false) {
            $string = str_replace($footnotePlaceholder, $footnoteReference, $string);
        }

        if ($url !== false) {
            $string = str_replace($urlPlaceholder, $url, $string);
        }

        if ($and !== false) {
            $string = str_replace($andPlaceholder, '\and ', $string);
        }

        return trim($string);
    }

    public static function calculateReportAuthorV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): array
    {
        $nameMacros = $latexFile->getMacros('author');

        $names = [];

        foreach($nameMacros as $macro) {
            $key = $macro->getOptions()[0] - 1;

            $name = new MetadataString($macro->getArgument());
            $names[$key] = $name->normalizeMacro()->getString();
        }

        return  $names;
    }

    public static function calculateReportAffiliationV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): array
    {
        $affiliationMacros = $latexFile->getMacros('affil');

        $affiliations = [];

        foreach($affiliationMacros as $macro) {
            $key = $macro->getOptions()[0] - 1;
            $affiliation = preg_replace('/\\\\texttt\{.*$/', '', $macro->getArgument());
            $affiliation = preg_replace('/, $|,$/', '', $affiliation);
            $affiliation = new MetadataString($affiliation);

            $affiliations[$key] = $affiliation->normalizeMacro()->getString();
        }

        return  $affiliations;
    }

    public static function calculateReportEmailV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): array
    {
        $affiliationMacros = $latexFile->getMacros('affil');

        $emails = [];

        foreach($affiliationMacros as $macro) {
            $key = $macro->getOptions()[0] - 1;
            preg_match('/\\\\texttt\{(.*)\}/', $macro->getArgument(), $match);


            $email = $match[0] ?? '';
            $email = new MetadataString($email);

            $emails[$key] = $email->normalizeMacro()->getString();
        }

        return  $emails;
    }

    public static function calculateReportEmptyAuthorArgV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): array
    {
        $affiliationMacros = $latexFile->getMacros('affil');

        $data = [];

        foreach($affiliationMacros as $macro) {
            $key = $macro->getOptions()[0] - 1;

            $data[$key] = '';
        }

        return $data;
    }

    public static function calculateTitleLipicsFrontmatterV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        return 'Front Matter, Table of Contents, Preface, Conference Organization';
    }

    public static function getReportFrontmatterTitleV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $string = trim($string);

        preg_match('/[0-9]{4}$/', $string, $match);

        $year = $match[0] ?? 0;

        $issueInfo = preg_replace('/,[ A-Za-z]*[0-9]{4,4}$/U', '', $string).', '.$year;

        return 'Dagstuhl Reports, Table of Contents, '.$issueInfo;
    }

    public static function getReportFrontmatterKeywordsV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        return 'Table of Contents, Frontmatter';
    }

    public static function calculateReportsTitleV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $title = $latexFile->getMacro('title')->getArgument();

        $title = self::reviseMacroV1($title, $originalString, $latexFile);

        $subject = trim($latexFile->getMacro('subject')->getArgument());

        preg_match('/[0-9]+$/', $subject, $match);

        $seminarNo = $match[0] ?? 'XXX unknown XXX';

        $isPW = stripos($subject, 'perspectives works') !== false;

        $seminarIdentifier = $isPW
            ? ' (Dagstuhl Perspectives Workshop '.$seminarNo.')'
            : ' (Dagstuhl Seminar '.$seminarNo.')';

        return $title. $seminarIdentifier;
    }

    private static function cleanupReportLatex(LatexFile $latexFile): LatexFile
    {
        $contents = $latexFile->getContents();

        $lines = explode("\n", $contents);

        foreach ($lines as $key => $line) {
            $lines[$key] = StringHelper::removeLeadingWhitespaces($line);
        }

        $contents = implode("\n", $lines);
        $contents = StringHelper::removeCarriageReturns($contents);
        $contents = preg_replace('/\\\\volumeinfo\s*\n\s*\{/sm', '\volumeinfo{', $contents);

        $latexFile->setContents($contents);

        return $latexFile;
    }

    public static function getReportFirstPageV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): int
    {
        $latexFile = self::cleanupReportLatex($latexFile);

        $volInfoMacro = $latexFile->getMacro('volumeinfo');

        return $volInfoMacro->getArguments()[5];
    }

    public static function getReportLastPageV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): int
    {
        $latexFile = self::cleanupReportLatex($latexFile);

        return self::getReportFirstPageV1($string, $originalString, $latexFile)
            + self::calculatePagesFromPdfV1($string, $originalString, $latexFile)
            - 1;
    }

    public static function calculateLicenseV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $style = $latexFile->getStyle()->getName();

        return in_array($style, [ 'lipics-v2021', 'oasics-v2021', 'lites-v2021' ])
            ? 'CC BY 4.0'
            : 'CC-BY 3.0';
    }

    public static function getSupplementCategoryV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $string = str_replace('\\\\', ' ', $string);
        return trim($string);
    }

    public static function getCiteOptionV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        return self::extractOption('cite', $string);
    }

    public static function getLinkTextOptionV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        return self::extractOption('linktext', $string);
    }

    public static function getSubCategoryOptionV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        return self::extractOption('subcategory', $string);
    }

    public static function getSoftwareHeritageIdOptionV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        return self::extractOption('swhid', $string);
    }

    public static function getRoleOptionV1(string $string, string $originalString = NULL, LatexFile $latexFile = NULL): string
    {
        $role = self::extractOption('role', $string);

        if ($role === 'author') {
            $role = '';
        }

        return $role;
    }

    public static function extractOption(string $name, string $string): string
    {
        preg_match('/'.$name.'\s*\=(.*)/', $string, $match);

        $match = $match[1] ?? '';

        $match = preg_replace('/^\s*\{/', '', $match);
        $match = preg_replace('/\s*\}\s*$/', '', $match);

        return trim($match);
    }


}
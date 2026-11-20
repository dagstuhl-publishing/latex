<?php


namespace Dagstuhl\Latex\Bibliography;


use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\LatexStructures\LatexString;
use Dagstuhl\Latex\Strings\MetadataString;
use Dagstuhl\Latex\Strings\StringHelper;
use Dagstuhl\Latex\Utilities\Filesystem;
use Exception;

class BblFile
{
    const BIBTEX_KEY_PATTERN = '/\\\\bibitem\{(.+)\}/';

    private string $path;
    private ?string $contents = null;

    /** @var string[] */
    private ?array $keys = null;

    private ?LatexFile $latexFile = null;

    public function __construct(string $path, LatexFile $latexFile = null)
    {
        $this->path = $path;
        $this->latexFile = $latexFile;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getContents(): ?string
    {
        if ($this->contents === null) {
            $this->contents = Filesystem::get($this->path);
        }

        return $this->contents;
    }

    /**
     * @return string[]
     */
    public function getKeys(): array
    {
        if ($this->keys === null) {
            preg_match_all(self::BIBTEX_KEY_PATTERN, $this->getContents(), $results, PREG_SET_ORDER, 0);
            $this->keys = array_column($results, 1);
        }

        return $this->keys;
    }

    /**
     * @param bool $objects
     * @return string[][]|Reference[]
     */
    public function getReferences(bool $objects = true): array
    {
        $bibKeyArray = [];

        $bblString = $this->getContents();

        $bblString = str_replace('\begin{thebibliography}{10}', '', $bblString);
        $bblString = str_replace('\end{thebibliography}', '', $bblString);

        $entries = explode('\bibitem', $bblString);

        unset($entries[0]);

        foreach ($entries as $key => $entry) {

            $entry = '\bibitem' . $entry;
            $originalEntry = $entry;

            $latexString = new LatexString($entry);

            $bibKey = NULL;

            // bibitem should actually occur exactly once
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
                $entry = str_replace($macro->getSnippet(), '\url{https://arxiv.org/abs/' . $arg . '}', $entry);
                //$entry = str_replace($macro->getSnippet(), '\href{https://arxiv.org/abs/'.$arg.'}{\path{arXiv:'.$arg.'}}', $entry);
            }

            $metaString = new MetadataString($entry, $this->latexFile);

            $metaString->expandMacros(true)->expandMacros(true)->normalizeMacro(true);

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

                // remove leading and trailing curly braces
                $url = preg_replace('/^\{*/', '', $url);
                $url = preg_replace('/\}*$/', '', $url);

                if (StringHelper::endsWith($cleanEntry, $url . '.')) {
                    $cleanEntry = str_replace($url . '.', '', $cleanEntry);
                }

                if (str_contains($url, 'doi')) {
                    $urlType = 'doi';
                } elseif (str_contains($url, 'arxiv') or str_contains($url, 'arXiv')) {
                    $urlType = 'arXiv';
                }

                if (StringHelper::endsWith($cleanEntry, 'URL: ')) {

                    // if cleanEntry already contains URL in plain text, then we do not append the URL at the end again
                    if (str_contains($cleanEntry, $url)) {
                        $cleanEntry = StringHelper::replace($cleanEntry, 'URL: ', '');
                    } else {
                        $cleanEntry .= $url . '.';
                    }
                } elseif (StringHelper::endsWith($cleanEntry, 'URL:')) {

                    // if cleanEntry already contains URL in plain text, then we do not append the URL at the end again
                    if (str_contains($cleanEntry, $url)) {
                        $cleanEntry = StringHelper::replace($cleanEntry, 'URL:', '');
                    } else {
                        $cleanEntry .= ' ' . $url . '.';
                    }
                } else {

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
            if (!mb_check_encoding($cleanEntry, 'UTF-8')) {
                $cleanEntry = '\\NON-UTF-8 CHAR CONTAINED! Check!';
            }

            $bibKeyArray[$bibKey] = [
                'order' => $key,
                'text' => $cleanEntry,
                'url' => $url,
                'urlType' => $urlType,
                'bibKey' => $bibKey,
                'internalData' => [ 'originalString' => $originalEntry ],
            ];
        }

        // now check for cross-references and replace them by the corresponding bibKey
        return $this->getRefsWithCorrectCrossRefs($bibKeyArray, $objects);
    }

    /**
     * @param string[][] $references
     * @return string[][]
     */
    private function getRefsWithCorrectCrossRefs(array $references, bool $objects = true): array
    {
        foreach($references as $bibKey => $entry) {

            $text = $entry['text'];
            $latexString = new LatexString($text);

            foreach ($latexString->getMacros('cite') as $macro) {

                $citeArgument = $macro->getArgument();

                if (!str_contains($citeArgument, ',')) {

                    try {
                        $refNumber = $references[$citeArgument]['order'];
                    } catch(Exception $ex) {
                        echo $ex->getMessage();
                        echo '<br>' . $citeArgument;
                    }

                } else {

                    $cites = explode(',',$citeArgument);

                    foreach ($cites as $cite) {

                        $refArray[] = $references[$cite]['order'];

                    }

                    $refNumber = implode(',', $refArray);

                }

                if (isset($refNumber)) {
                    $correctCitedText = str_replace($macro->getSnippet(), '['. $refNumber . ']', $text);
                } else {
                    $correctCitedText = str_replace($macro->getSnippet(), '[ ??? ]', $text);
                }

                $references[$bibKey]['text'] = $correctCitedText;
            }


            $correctCitedText = str_replace('. {','. ', $references[$bibKey]['text']);
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

            $references[$bibKey]['text'] = $correctCitedText;

        }

        return $objects
            ? array_map(fn($r) => Reference::fromArray($r), $references)
            : $references;
    }
}
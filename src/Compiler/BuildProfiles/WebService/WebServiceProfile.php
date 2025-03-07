<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\WebService;

use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Dagstuhl\Latex\Compiler\BuildProfiles\Utilities\ParseExitCodes;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;

class WebServiceProfile extends BasicProfile implements BuildProfileInterface
{
    use ParseExitCodes;

    protected string $apiUrl;
    protected array $pathReplacement = [
        'searchRegex' => NULL,
        'replacement' => NULL
    ];

    public function __construct(LatexFile $latexFile = NULL, string $apiUrl = NULL)
    {
        parent::__construct($latexFile);
        $this->apiUrl = config('latex.profiles.web-service.api-url') ?? $apiUrl;
    }

    public function setPathReplacement(string $searchRegex, string $replace): void
    {
        $this->pathReplacement['searchRegex'] = $searchRegex;
        $this->pathReplacement['replacement'] = $replace;
    }

    public function setApiUrl(string $apiUrl): void
    {
        $this->apiUrl = $apiUrl;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function compile(array $options = []): void
    {
        $modeParam = $shellEscapeParam = '';
        if (!empty($options['mode'])) {
            $modeParam = '&mode='.$options['mode'];
        }
        if (isset($options['shell-escape']) && $options['shell-escape']) {
            $shellEscapeParam = '&shell-escape=1';
        }

        $path = Filesystem::storagePath($this->latexFile->getPath());
        if ($this->pathReplacement['searchRegex'] !== NULL) {
            $path = preg_replace($this->pathReplacement['searchRegex'], $this->pathReplacement['replacement'], $path);
        }

        $pathParam = '?path='.$path;

        $out = @file_get_contents($this->apiUrl . $pathParam . $modeParam . $shellEscapeParam);
        $out = explode("\n", $out);
        $this->profileOutput = $out;

        $lastLine = $out[count($out)-1];
        list($this->latexExitCode, $this->bibtexExitCode) = $this->parseExitCodes($lastLine);
    }

    public function getLatexVersion(): string
    {
        return @file_get_contents($this->apiUrl.'?path='.$this->latexFile->getPath().'&mode=version');
    }
}

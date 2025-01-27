<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\WebService;

use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal\ParseExitCodes;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;

class WebServiceProfile extends BasicProfile implements BuildProfileInterface
{
    use ParseExitCodes;

    protected string $apiUrl;

    public function __construct(LatexFile $latexFile = NULL, string $apiUrl = NULL)
    {
        parent::__construct($latexFile);
        $this->apiUrl = config('latex.profiles.web-service.api-url') ?? $apiUrl;
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
        $modeParam = '';
        if (!empty($options['mode'])) {
            $modeParam = '&mode='.$options['mode'];
        }

        $pathParam = '?path='.Filesystem::storagePath($this->latexFile->getPath());

        $out = @file_get_contents($this->apiUrl . $pathParam . $modeParam);
        $out = explode("\n", $out);

        $lastLine = $out[count($out)-1];

        $out = array_merge([ 'Profile: WebServiceProfile' ], $out);
        $this->profileOutput = $out;

        list($this->latexExitCode, $this->bibtexExitCode) = $this->parseExitCodes($lastLine);
    }

    public function getLatexVersion(): string
    {
        return @file_get_contents($this->apiUrl.'?path='.$this->latexFile->getPath().'&mode=version');
    }
}
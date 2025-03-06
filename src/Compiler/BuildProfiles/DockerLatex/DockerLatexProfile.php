<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\DockerLatex;

use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Dagstuhl\Latex\Compiler\BuildProfiles\ParseExitCodes;
use Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal\PdfLatexBibtexLocalProfile;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Environment;
use Dagstuhl\Latex\Utilities\Filesystem;
use Exception;
use GuzzleHttp\Client;
use Phar;
use PharData;
use Throwable;

class DockerLatexProfile extends BasicProfile implements BuildProfileInterface
{
    use ParseExitCodes;

    protected Client $httpClient;
    protected string $apiUrl;
    protected array $headers;

    const BUILD_SCRIPT = __DIR__.'/../PdfLatexBibtexLocal/pdflatex-bibtex-local.sh';
    const BUILD_SCRIPT_NAME = '_latex-build.sh';
    const DEFAULT_DOCKER_PROFILE = 'texlive:2024';


    public function __construct(LatexFile $latexFile, array $globalOptions = [])
    {
        $globalOptions['docker-profile'] = $globalOptions['docker-profile'] ?? static::DEFAULT_DOCKER_PROFILE;

        parent::__construct($latexFile, $globalOptions);

        $this->httpClient = new Client();
        $this->apiUrl = config('latex.docker-latex.api-url') ?? $globalOptions['api-url'];
        $this->headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . config('latex.docker-latex.token')
        ];
    }

    private function getArchiveDirectory(): string
    {
        $sourceFolder = $this->latexFile->getDirectory();
        return (config('latex.paths.temp') ?? '') .'/'.md5($sourceFolder);
    }

    private function getTarFilePath(string $extension = ''): string
    {
        if ($extension !== '' && !str_starts_with('.', $extension)) {
            $extension = '.'.$extension;
        }

        $texFolderName = preg_replace('/\.tex$/', '', $this->latexFile->getFilename());
        return $this->getArchiveDirectory() . '/' . $texFolderName . '.tar' . $extension;
    }

    private function unlinkArchive(string $path): void
    {
        // to resolve phar caching issue
        try {
            PharData::unlinkArchive($path);
        }
        catch(Throwable $ex) {}
    }

    public function archiveSource(): ?string
    {
        $sourceFolder = $this->latexFile->getDirectory();
        $targetFolder = $this->getArchiveDirectory();
        $targetFile = $this->getTarFilePath();

        copy(static::BUILD_SCRIPT, $sourceFolder.'/'.static::BUILD_SCRIPT_NAME);
        chmod($sourceFolder.'/'.static::BUILD_SCRIPT_NAME, 0755);

        Filesystem::deleteDirectory($targetFolder, true);
        Filesystem::makeDirectory($targetFolder, true);

        $this->unlinkArchive($targetFile);

        $archive = new PharData($targetFile);
        $archive->buildFromDirectory($sourceFolder);
        $archive->compress(Phar::GZ);
        unset($archive);
        $this->unlinkArchive($targetFile);

        $targetFile .= '.gz';

        return file_exists($targetFile)
            ? $targetFile
            : NULL;
    }

    public function unTarArchive(): void
    {
        $targetFolder = $this->latexFile->getDirectory();

        // unzip to temp folder
        exec('cd '.$this->getArchiveDirectory(). ' && gunzip '.$this->getTarFilePath('gz'));

        // clean target folder and extract tar there
        Filesystem::deleteDirectory($targetFolder, true);
        Filesystem::makeDirectory($targetFolder, true);

        $archive = new PharData($this->getTarFilePath());
        $archive->extractTo($targetFolder);
        unset($archive);
        $this->unlinkArchive($this->getTarFilePath());

        Filesystem::deleteDirectory($this->getArchiveDirectory());
    }


    private function createContext(string $command, string $pathToArchive): ?array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/context/new', [
                'headers' => $this->headers,
                'multipart' => [
                    [
                        'name' => 'profile',
                        'contents' => $this->globalOptions['docker-profile']
                    ],
                    [
                        'name' => 'texFile',
                        'contents' => $this->latexFile->getFilename()
                    ],
                    [
                        'name' => 'logFile',
                        'contents' => preg_replace('/\.tex$/', '.log', $this->latexFile->getFilename())
                    ],
                    [
                        'name' => 'pdfFile',
                        'contents' => preg_replace('/\.tex$/', '.pdf', $this->latexFile->getFilename())
                    ],
                    [
                        'name' => 'commands',
                        'contents' => $command
                    ],
                    [
                        'name' => 'archive',
                        'contents' => fopen($this->getTarFilePath('gz'), 'r')
                    ],
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        }
        catch(Exception $ex) {
            return NULL;
        }
    }

    private function build(array $context): ?array
    {
        $context = $context['name'];

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/context/'.$context.'/build', [
                'headers' => $this->headers,
            ]);

            $status = json_decode($response->getBody()->getContents(), true);
        }
        catch(Exception $ex) {
            return NULL;
        }
    }

    private function getEnvironmentVariables(array $options): array
    {
        $defaultProfile = new PdfLatexBibtexLocalProfile($this->latexFile, $this->globalOptions);
        $env = $defaultProfile->getEnvironmentVariables($options, true);
        $env['HOME'] = '/tmp';
        unset($env['PATH']);

        return $env;
    }

    public function compile(array $options = []): void
    {
        $archive = $this->archiveSource();

        if ($archive === NULL) {
            // TODO
        }

        $env = $this->getEnvironmentVariables($options);
        $command = Environment::toString($env) . ' ./' . static::BUILD_SCRIPT_NAME;

        $context = $this->createContext($command, $archive); // TODO: choose profile in LaTeX file

        if ($context === NULL) {
            // TODO
        }

        $status = $this->build($context);

        $out = $status['buildStdOut'];
        $lastLine = $out[count($out)-1];
        list($this->latexExitCode, $this->bibtexExitCode) = $this->parseExitCodes($lastLine);


    }

    public function getLatexVersion(): string
    {
        // TODO: Implement getLatexVersion() method.
    }
}
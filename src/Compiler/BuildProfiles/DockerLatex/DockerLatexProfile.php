<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\DockerLatex;

use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal\PdfLatexBibtexLocalProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\Utilities\GetRequestedLatexVersion;
use Dagstuhl\Latex\Compiler\BuildProfiles\Utilities\ParseExitCodes;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Environment;
use Dagstuhl\Latex\Utilities\Filesystem;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Phar;
use PharData;
use Throwable;

class DockerLatexProfile extends BasicProfile implements BuildProfileInterface
{
    use ParseExitCodes;
    use GetRequestedLatexVersion;

    protected Client $httpClient;
    protected string $apiUrl;
    protected array $headers;

    protected string $dockerProfile;


    const BUILD_SCRIPT = __DIR__.'/../PdfLatexBibtexLocal/pdflatex-bibtex-local.sh';
    const BUILD_SCRIPT_NAME = '_latex-build.sh';
    const DEFAULT_DOCKER_PROFILE = 'texlive:2024';


    public function __construct(LatexFile $latexFile = NULL, array $globalOptions = [])
    {
        parent::__construct($latexFile, $globalOptions);

        if ($latexFile !== NULL) {
            $this->setLatexFile($latexFile);
        }

        $this->httpClient = new Client();
        $this->apiUrl = config('latex.profiles.docker-latex.api-url') ?? $globalOptions['api-url'];
        $this->headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . config('latex.profiles.docker-latex.token')
        ];
    }

    public function setLatexFile(?LatexFile $latexFile): void
    {
        $version = NULL;
        if ($latexFile !== NULL) {
            $version = static::getRequestedLatexVersion($latexFile);
            if (!empty($version)) {
                $version = 'texlive:' . $version;
            }
        }

        $this->dockerProfile = $version ?? $globalOptions['docker-profile'] ?? static::DEFAULT_DOCKER_PROFILE;

        parent::setLatexFile($latexFile);
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
        $sourceFolder = Filesystem::storagePath($this->latexFile->getDirectory());
        $targetFolder = $this->getArchiveDirectory();
        $targetFile = $this->getTarFilePath();

        copy(static::BUILD_SCRIPT, $sourceFolder.'/'.static::BUILD_SCRIPT_NAME);
        chmod($sourceFolder.'/'.static::BUILD_SCRIPT_NAME, 0755);

        Filesystem::deleteDirectory($targetFolder, true);
        Filesystem::makeDirectory($targetFolder, true);

        $this->unlinkArchive($targetFile);

        // remove PDF to save resources/traffic
        Filesystem::delete($this->latexFile->getPath('pdf'));

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

        exec('cd '.$this->getArchiveDirectory(). ' && gunzip '.escapeshellarg($this->getTarFilePath('gz')));

        // clean target folder and extract tar there
        Filesystem::deleteDirectory($targetFolder, true);
        Filesystem::makeDirectory($targetFolder, true);

        $archive = new PharData($this->getTarFilePath());
        $archive->delete('.');
        $archive->extractTo($targetFolder);
        unset($archive);
        $this->unlinkArchive($this->getTarFilePath());

        Filesystem::deleteDirectory($this->getArchiveDirectory());
    }


    /**
     * @throws GuzzleException
     */
    private function createContext(string $command, string $pathToArchive): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl . '/context/new', [
            'headers' => $this->headers,
            'multipart' => [
                [
                    'name' => 'profile',
                    'contents' => $this->dockerProfile
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

    /**
     * @throws GuzzleException
     */
    private function buildContext(array $context): array
    {
        $context = $context['name'];
        $response = $this->httpClient->request('POST', $this->apiUrl . '/context/'.$context.'/build', [
            'headers' => $this->headers,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @throws GuzzleException
     */
    private function downloadContext(array $context): void
    {
        $context = $context['name'];
        $this->httpClient->request('GET', $this->apiUrl . '/context/'.$context.'/archive', [
            'headers' => $this->headers,
            'sink' => $this->getTarFilePath('gz')
        ]);
    }

    /**
     * @throws GuzzleException
     */
    private function deleteContext(array $context): void
    {
        $context = $context['name'];
        $this->httpClient->request('DELETE', $this->apiUrl . '/context/'.$context, [
            'headers' => $this->headers,
        ]);
    }

    private function getEnvironmentVariables(array $options): array
    {
        $defaultProfile = new PdfLatexBibtexLocalProfile($this->latexFile, $this->globalOptions);
        $env = $defaultProfile->getEnvironmentVariables($options, true);
        $env['HOME'] = '/tmp';
        unset($env['PATH']);

        return $env;
    }

    private function buildFailed(string $step, string $message): void
    {
        $this->profileOutput = [
            'Build failed in Step '.$step,
            'Error message: '.$message
        ];

        var_dump($this->profileOutput);

        $this->latexExitCode = NULL;
        $this->bibtexExitCode = NULL;
    }

    public function compile(array $options = []): void
    {
        $env = $this->getEnvironmentVariables($options);
        $command = Environment::toString($env) . ' ./' . static::BUILD_SCRIPT_NAME. ' '.$this->latexFile->getFilename();

        try {
            $step = '[archive source]';
            $archive = $this->archiveSource();

            $step = '[create context]';
            $context = $this->createContext($command, $archive);

            $step = '[build context]';
            $status = $this->buildContext($context);

            $step = '[download context]';
            $this->downloadContext($context);

            $step = '[un-compress archive]';
            $this->unTarArchive();

            $step = '[delete context]';
            $this->deleteContext($context);
        }
        catch (Throwable $ex) {
            $this->buildFailed($step, $ex->getMessage());
            return;
        }

        $out = explode("\n", trim($status['buildStdout']));
        $this->profileOutput = $out;
        $lastLine = $out[count($out)-1];
        list($this->latexExitCode, $this->bibtexExitCode) = $this->parseExitCodes($lastLine);
    }

    public function getLatexVersion(): string
    {
        $response = $this->httpClient->request('GET', $this->apiUrl . '/profiles/'.$this->dockerProfile, [
            'headers' => $this->headers,
        ]);

        try {
            $result = json_decode($response->getBody()->getContents(), true);
            return $result['latexVersion'];
        }
        catch(Throwable $ex) {
            return 'Unable to determine LaTeX version: '.$ex->getMessage();
        }
    }
}
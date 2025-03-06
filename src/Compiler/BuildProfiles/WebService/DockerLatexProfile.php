<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\WebService;

use Dagstuhl\Latex\Compiler\BuildProfiles\PdfLatexBibtexLocal\PdfLatexBibtexLocalProfile;
use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Environment;
use Dagstuhl\Latex\Utilities\Filesystem;
use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use GuzzleHttp\Client;
use Phar;
use PharData;
use Throwable;

class DockerLatexProfile extends BasicProfile implements BuildProfileInterface
{
    protected Client $httpClient;
    protected string $apiUrl;
    protected array $requestOptions;


    public function __construct(LatexFile $latexFile, array $options = [])
    {
        parent::__construct($latexFile, $options);

        $this->httpClient = new Client();
        $this->apiUrl = config('latex.docker-latex.api-url') ?? $options['api-url'];
        $this->requestOptions = [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . config('latex.docker-latex.token')
            ]
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

        copy(__DIR__.'/../PdfLatexBibtexLocal/pdflatex-bibtex-local.sh', $sourceFolder.'/_latex-build.sh');
        chmod($sourceFolder.'/_latex-build.sh', 0755);

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


    public function compile(array $options = []): void
    {
        $defaultProfile = new PdfLatexBibtexLocalProfile($this->latexFile, $this->globalOptions);

        $env = $defaultProfile->getEnvironmentVariables($options, true);
        $env['HOME'] = '/tmp';
        unset($env['PATH']);

        echo Environment::toString($env);
    }

    public function getLatexVersion(): string
    {
        // TODO: Implement getLatexVersion() method.
    }
}
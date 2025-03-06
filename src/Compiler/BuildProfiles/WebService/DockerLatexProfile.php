<?php

namespace Dagstuhl\Latex\Compiler\BuildProfiles\WebService;

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;
use Dagstuhl\Latex\Compiler\BuildProfiles\BasicProfile;
use Dagstuhl\Latex\Compiler\BuildProfiles\BuildProfileInterface;
use Phar;
use PharData;

class DockerLatexProfile extends BasicProfile implements BuildProfileInterface
{
    public function archivedSourcePath(): ?string
    {
        $sourceFolder = $this->latexFile->getDirectory();
        $hash = md5($sourceFolder);

        $targetFolder = config('latex.paths.temp').'/'.$hash;

        Filesystem::deleteDirectory($targetFolder, true);
        Filesystem::makeDirectory($targetFolder, true);

        $targetFile = $targetFolder.'/archive.tar';

        $archive = new PharData($targetFile);
        $archive->buildFromDirectory($sourceFolder);
        $archive->compress(Phar::GZ);
        unlink($targetFile);
        $targetFile .= '.gz';

        return file_exists($targetFile)
            ? $targetFile
            : NULL;
    }

    public function compile(): void
    {
        // TODO: Implement compile() method.
    }

    public function getLatexVersion(): string
    {
        // TODO: Implement getLatexVersion() method.
    }
}
<?php


namespace Dagstuhl\Latex\Bibliography;


use Exception;
use Dagstuhl\Latex\Utilities\Filesystem;

class BblFile
{
    const BIBTEX_KEY_PATTERN = '/\\\\bibitem\{(.+)\}/';

    private string $path;
    private ?string $contents = null;

    /** @var string[] */
    private ?array $keys = null;

    public function __construct(string $path)
    {
        $this->path = $path;
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
}
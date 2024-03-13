<?php


namespace Dagstuhl\Latex\Bibliography;


use Exception;
use Dagstuhl\Latex\Utilities\Filesystem;

class BblFile
{
    const BIBTEX_KEY_PATTERN = '/\\\\bibitem\{(.+)\}/';

    private string $path;
    private ?string $contents;

    /** @var string[] */
    private array $keys;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->contents = Filesystem::get($this->path);

        preg_match_all(self::BIBTEX_KEY_PATTERN, $this->contents, $results, PREG_SET_ORDER, 0);

        $this->keys = array_column($results, 1);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getContents(): ?string
    {
        return $this->contents;
    }

    /**
     * @return string[]
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
<?php
declare(strict_types=1);

namespace App\Repository;


use App\Entity\Brew;
use Symfony\Component\Finder\Finder;

class BrewRepository
{
    /**
     * @var string
     */
    private $brewDir;

    public function __construct(string $brewDir)
    {
        $this->brewDir = rtrim(realpath($brewDir), DIRECTORY_SEPARATOR);
    }

    public function findAll(): array
    {
        $finder = new Finder();
        $finder->files()
            ->in($this->brewDir)
            ->name("brew_*.meta_v1.json")
            ->sortByName()
        ;

        $brews = [];
        foreach($finder as $file) {
            if (preg_match("/brew_(\d+\.\d+)\.meta_v1\.json/", $file->getFilename(), $matches)) {
                $brews[] = new Brew($matches[1], $file->getRealPath(), null);
            }
        }

        return $brews;
    }

    public function find(string $id): ?Brew
    {
        $basePath = sprintf("%s/brew_%s", $this->brewDir, $id);
        $metaFile = $basePath.".meta_v1.json";
        $dataFile = $basePath.".brewfile_v1.csv";

        if (!file_exists($metaFile) || !file_exists($dataFile)) {
            return null;
        }

        if (strpos(realpath($metaFile), $this->brewDir) !== 0) {
            return null;
        }

        if (strpos(realpath($dataFile), $this->brewDir) !== 0) {
            return null;
        }

        try {
            return new Brew($id, realpath($metaFile), realpath($dataFile));
        } catch (\Exception $e) {
            return null;
        }
    }
}

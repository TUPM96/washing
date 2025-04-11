<?php

namespace App\Console\Commands;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

class BaseSeed
{
    /**
     * Name of the database upon which the seed will be executed.
     *
     * @var string
     */
    protected $databaseName;

    /**
     * @var Filesystem|null
     */
    private $files;

    /**
     * BaseSeed constructor.
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->files = $filesystem;
    }

    /**
     * Generates a seed file.
     *
     * @param $seederName
     * @return string
     * @throws FileNotFoundException
     */
    public function getSeederContent($seederName)
    {
        // Generate class name
        $className = $this->generateClassName($seederName);

        // Get a seed folder path
        $seedPath = $this->getSeedPath();

        // Get a app/database/seeds path
        $seedsPath = $this->getPath($className, $seedPath);
        
        return $this->files->get($seedsPath);
    }

    /**
     * Generates a seed class name (also used as a filename)
     * @param $seederName
     * @return string
     */
    public function generateClassName($seederName)
    {
        return $seederName . 'Seeder';
    }

    /**
     * Get a seed folder path
     * @return string
     */
    public function getSeedPath()
    {
        return base_path() . '/database/seeders';
    }

    /**
     * Create the full path name to the seed file.
     * @param $name
     * @param $path
     * @return string
     */
    public function getPath($name, $path)
    {
        return $path . '/' . $name . '.php';
    }

    /**
     * Put seeder file
     *
     * @param $seederName
     * @param $seederContent
     * @return bool|int
     */
    public function putSeederFile($seederName, $seederContent)
    {
        // Generate class name
        $className = $this->generateClassName($seederName);

        // Get a seed folder path
        $seedPath = $this->getSeedPath();

        // Get a app/database/seeds path
        $seedsPath = $this->getPath($className, $seedPath);

        return $this->files->put($seedsPath, $seederContent);
    }

    /**
     * Replace content between
     *
     * @param $str
     * @param $needleStart
     * @param $needleEnd
     * @param $replacement
     * @return string|string[]
     */
    public function replaceBetween($str, $needleStart, $needleEnd, $replacement)
    {
        $pos = strpos($str, $needleStart);
        $start = $pos === false ? 0 : $pos + strlen($needleStart);

        $pos = strpos($str, $needleEnd, $start);
        $end = $start === false ? strlen($str) : $pos;

        return substr_replace($str, $replacement, $start, $end - $start);
    }
}
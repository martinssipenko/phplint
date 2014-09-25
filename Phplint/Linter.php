<?php

namespace Phplint;

use Symfony\Component\Finder\Finder;
use Phplint\Process\Lint;

class Linter
{
    /** @var callable */
    private $processCallback;

    private $files = false;

    private $path;
    private $excludes;
    private $extensions;

    private $procLimit = 5;

    public function __construct($path, $excludes, $extensions)
    {
        $this->path       = $path;
        $this->excludes   = $excludes;
        $this->extensions = $extensions;
    }

    public function lint($files)
    {
        $processCallback = is_callable($this->processCallback) ? $this->processCallback : function() {};

        $errors   = array();
        $running  = array();
        $newCache = array();

        while ($files || $running) {
            for ($i = count($running); $files && $i < $this->procLimit; $i++) {
                $file     = array_shift($files);
                $fileName = $file->getRealpath();

                if (!isset($this->cache[$fileName]) || $this->cache[$fileName] !== md5_file($fileName)) {
                    $running[$fileName] = new Lint(PHP_BINARY.' -l '.$fileName);
                    $running[$fileName]->start();
                }
            }

            foreach ($running as $fileName => $lintProcess) {
                if (!$lintProcess->isRunning()) {
                    unset($running[$fileName]);
                    if ($lintProcess->hasSyntaxError()) {
                        $processCallback('error', $fileName);
                        $errors[$fileName] = $lintProcess->getSyntaxError();
                    } else {
                        $newCache[$fileName] = md5_file($fileName);
                        $processCallback('ok', $file);
                    }
                }
            }

            file_put_contents(__DIR__.'/../phplint.cache', json_encode($newCache));
        }

        return $errors;
    }

    public function setCache($cache = array())
    {
        if (is_array($cache)) {
            $this->cache = $cache;
        } else {
            $this->cache = array();
        }
    }

    public function getFiles()
    {
        if (!$this->files) {
            $this->files = new Finder();
            $this->files->files()->in(realpath($this->path));

            foreach ($this->excludes as $exclude) {
                $this->files->notPath($exclude);
            }

            foreach ($this->extensions as $extension) {
                $this->files->name('*.'.$extension);
            }

            $this->files = iterator_to_array($this->files);
        }

        return $this->files;
    }

    /**
     * @param callable $processCallback
     * @return ParallelLint
     */
    public function setProcessCallback($processCallback)
    {
        $this->processCallback = $processCallback;

        return $this;
    }

    public function setProcessLimit($procLimit)
    {
        $this->procLimit = $procLimit;

        return $this;
    }
}

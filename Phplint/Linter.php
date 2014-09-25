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

        $errors  = array();
        $running = array();

        while ($files || $running) {
            for ($i = count($running); $files && $i < $this->procLimit; $i++) {
                $file     = array_shift($files);
                $fileName = $file->getRealpath();

                $running[$fileName] = new Lint(PHP_BINARY.' -l '.$fileName);
                $running[$fileName]->start();
            }

            foreach ($running as $fileName => $lintProcess) {
                if (!$lintProcess->isRunning()) {
                    unset($running[$fileName]);
                    if ($lintProcess->hasSyntaxError()) {
                        $processCallback('error', $fileName);
                        $errors[$fileName] = $lintProcess->getSyntaxError();
                    } else {
                        $processCallback('ok', $file);
                    }
                }
            }
        }

        return $errors;
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

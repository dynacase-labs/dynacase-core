<?php
namespace Dcp;

class CrontabSectionElement extends CrontabElement
{
    public $contextRoot;
    public $file;
    /**
     * CrontabParserSectionElement constructor.
     *
     * @param string $contextRoot
     * @param string $file
     */
    public function __construct($contextRoot, $file)
    {
        $this->contextRoot = $this->normalizeContextRoot($contextRoot);
        $this->file = $this->normalizeFile($file);
    }
    /**
     * Cleanup path names
     *
     * @param string $path
     * @return string
     */
    public function normalizeFile($path)
    {
        /* Remove trailing slashes */
        $path = rtrim($path, '/');
        /* Replace multiples slashes with single slash */
        $path = preg_replace(':/+:', '/', $path);
        /* Remove leading relative './' notation */
        $path = preg_replace(':^\./:', '', $path);
        return $path;
    }
    /**
     * Cleanup path names and try to resolve absolute path
     *
     * @param string $path
     * @return string
     */
    public function normalizeContextRoot($path)
    {
        $path = $this->normalizeFile($path);
        /* Try to resolve "real" path (to get rid of '../' elements) */
        if (is_dir($path) && ($realPath = realpath($path)) !== false) {
            $path = $realPath;
        }
        return $path;
    }
    /**
     * Check if the section match the given context
     *
     * @param string $contextRoot The context's root directory
     * @return bool
     */
    public function matchContextRoot($contextRoot)
    {
        return ($this->contextRoot === $this->normalizeContextRoot($contextRoot));
    }
    /**
     * Check if the section match the given file
     *
     * @param string $file The cron file relative to the context's root directory
     * @return bool
     */
    public function matchFile($file)
    {
        return ($this->file === $this->normalizeFile($file));
    }
    /**
     * Check if the section match the given context and file
     *
     * @param string $contextRoot The context's root directory
     * @param string $file The cron file relative to the context's root directory
     * @return bool
     */
    public function match($contextRoot, $file)
    {
        return ($this->matchContextRoot($contextRoot) && $this->matchFile($file));
    }
    /**
     * Serialize element to string
     *
     * @return string
     */
    public function __toString()
    {
        $lines = array();
        $lines[] = sprintf("# BEGIN:FREEDOM_CRONTAB:%s:%s", $this->contextRoot, $this->file);
        foreach ($this->childs as & $child) {
            $lines[] = (string)$child;
        }
        $lines[] = sprintf("# END:FREEDOM_CRONTAB:%s:%s", $this->contextRoot, $this->file);
        return implode("\n", $lines);
    }
}

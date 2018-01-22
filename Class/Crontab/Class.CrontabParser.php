<?php
namespace Dcp;

class CrontabParser
{
    const REGEXP_SECTION_BEGIN = '/^#\s+BEGIN:FREEDOM_CRONTAB:(?P<contextRoot>[^:]+):(?P<file>.*)$/';
    const REGEXP_SECTION_END = '/^#\s+END:FREEDOM_CRONTAB:(?P<contextRoot>[^:]+):(?P<file>.*)$/';
    /**
     * Parse a crontab into Text and Section elements
     *
     * @param string $crontab
     * @return CrontabDocument
     * @throws CrontabParserException
     */
    public function parse($crontab)
    {
        $lines = explode("\n", $crontab);
        /**
         * @var $currentSection CrontabSectionElement
         */
        $currentSection = null;
        $crontabDocument = new CrontabDocument();
        $i = 0;
        while ($i < count($lines)) {
            $line = $lines[$i];
            if ($currentSection !== null) {
                if (preg_match(self::REGEXP_SECTION_END, $line, $m)) {
                    if (!$currentSection->match($m['contextRoot'], $m['file'])) {
                        throw new CrontabParserException(sprintf("Section end mismatch at line #%d: expecting '%s:%s', found '%s:%s'", ($i + 1) , $currentSection->contextRoot, $currentSection->file, $m['contextRoot'], $m['file']));
                    }
                    $crontabDocument->appendChild($currentSection);
                    $currentSection = null;
                } else {
                    $newTextElement = new CrontabTextElement($line);
                    $currentSection->appendChild($newTextElement);
                }
            } else {
                if (preg_match(self::REGEXP_SECTION_BEGIN, $line, $m)) {
                    if ($currentSection !== null) {
                        throw new CrontabParserException(sprintf("Beginning of section found while already in a section at line #%d", ($i+1)));
                    }
                    $currentSection = new CrontabSectionElement($m['contextRoot'], $m['file']);
                } else {
                    $newTextElement = new CrontabTextElement($line);
                    $crontabDocument->appendChild($newTextElement);
                }
            }
            $i++;
        }
        if ($currentSection !== null) {
            throw new CrontabParserException(sprintf("Section end not found at line #%d", ($i+1)));
        }

        return $crontabDocument;
    }
}

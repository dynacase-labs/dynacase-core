<?php
namespace Dcp;

class CrontabTextElement extends CrontabElement
{
    public $text;
    /**
     * CrontabParserTextElement constructor.
     * @param string $text
     */
    public function __construct($text)
    {
        $this->text = $text;
    }
    /**
     * Serialize element to string
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->text;
    }
}

<?php
namespace Dcp;

class CrontabDocument extends CrontabElement
{
    public function __toString()
    {
        $previousElement = null;
        /* Coalesce empty text elements */
        $elements = array_filter($this->childs, function ($currentElement) use (&$previousElement)
        {
            if ($previousElement !== null && is_a($previousElement, '\Dcp\CrontabTextElement') && is_a($currentElement, '\Dcp\CrontabTextElement')) {
                if (strlen((string)$previousElement) <= 0 && strlen((string)$currentElement) <= 0) {
                    return false;
                }
            }
            $previousElement = $currentElement;
            return true;
        });
        return join("\n", $elements) . "\n";
    }
}

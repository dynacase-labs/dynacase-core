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
            if ($previousElement !== null && is_a($previousElement, CrontabTextElement::class) && is_a($currentElement, CrontabTextElement::class))
            {
                if (strlen((string)$previousElement) <= 0 && strlen((string)$currentElement) <= 0) {
                    return false;
                }
            }
            $previousElement = $currentElement;
            return true;
        });
        return join("\n", $elements);
    }
}

<?php
namespace Dcp;

class CrontabElement
{
    public $childs = array();
    /**
     * @param CrontabElement $element
     * @return $this
     */
    public function appendChild(CrontabElement & $element)
    {
        $this->childs[] = $element;
        return $this;
    }
}

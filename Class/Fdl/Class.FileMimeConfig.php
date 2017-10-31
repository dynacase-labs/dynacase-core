<?php
namespace Dcp;

class FileMimeConfig
{
    //region Constants
    
    /**
     * Default sys & user config file locations
     */
    const CONFIG_USER = 'config/file-mime-user.xml';
    const CONFIG_SYS = 'config/file-mime.xml';
    /**
     * MIME type pattern specificity
     */
    const SPECIFICITY_ANY = 0; // Match any MIME type (pattern "*/*")
    const SPECIFICITY_TYPE_ONLY = 1; // Match type (but not subtype) (pattern "xxx/*")
    const SPECIFICITY_TYPE_AND_SUBTYPE = 2; // Match type and subtype (pattern "xxx/xxx")
    
    /**
     * Targets: 'deny', 'allow'
     */
    const TARGET_DENY = 'deny';
    const TARGET_ALLOW = 'allow';
    //endregion
    //region Properties
    
    /**
     * @var array
     */
    protected $inlineRules = array(
        '*/*' => self::TARGET_DENY
    );
    //endregion
    //region public
    
    /**
     * FileMime constructor.
     * @param array $configFiles
     */
    public function __construct($configFiles = array())
    {
        if (count($configFiles) <= 0 || !is_array($configFiles)) {
            $configFiles = array(
                DEFAULT_PUBDIR . '/' . self::CONFIG_SYS,
                DEFAULT_PUBDIR . '/' . self::CONFIG_USER
            );
        }
        foreach ($configFiles as $configFile) {
            $this->loadConfig($configFile);
        }
    }
    /**
     * @param string $mime
     * @return bool
     */
    public static function isWellformedMIMEType($mime)
    {
        return (preg_match('!^[^ /]+/[^ /]+$!', $mime) === 1);
    }
    /**
     * Match a MIME type (e.g. 'application/x-acme-file') to a MIME pattern (e.g. 'application/*')
     *
     * @param string $mime The MIME string to test
     * @param string $pattern The MIME pattern with which the
     * @return bool
     */
    public static function mimeMatch($mime, $pattern)
    {
        if (!self::isWellformedMIMEType($mime)) {
            /* Invalid MIME type */
            return false;
        }
        $patternSpecificity = self::getMimePatternSpecificity($pattern);
        if ($patternSpecificity == self::SPECIFICITY_TYPE_AND_SUBTYPE) {
            return ($mime === $pattern);
        } elseif ($patternSpecificity == self::SPECIFICITY_TYPE_ONLY) {
            $type_slash = substr($pattern, 0, -1); /* Keep the type with slash delimiter ('xxx/*' -> 'xxx/') for matching */
            return (substr($mime, strlen($type_slash)) === $type_slash);
        }
        return true;
    }
    /**
     * Check if a given MIME type is allowed
     *
     * @param string $mime The MIME type to check (e.g. 'application/x-acme-file')
     * @return bool
     */
    public function isInlineAllowed($mime)
    {
        foreach ($this->inlineRules as $rulePattern => $ruleTarget) {
            if ($this->mimeMatch($mime, $rulePattern)) {
                return ($ruleTarget === self::TARGET_ALLOW);
            }
        }
        return false;
    }
    //endregion
    //region protected
    
    /**
     * @param string $file
     * @return bool
     */
    protected function loadConfig($file)
    {
        if (!is_scalar($file)) {
            return false;
        }
        if (!file_exists($file)) {
            return false;
        }
        if (($xml = simplexml_load_file($file)) === false) {
            return false;
        }
        if (!isset($xml->inline)) {
            return false;
        }
        $inlineRules = array();
        /**
         * @var \SimpleXMLElement $rule
         */
        foreach ($xml->inline->children() as $rule) {
            $target = $rule->getName();
            if ($target != self::TARGET_DENY && $target != self::TARGET_ALLOW) {
                continue;
            }
            $attrs = $rule->attributes();
            if (!isset($attrs['type'])) {
                continue;
            }
            $type = (string)$attrs['type'];
            $inlineRules[$type] = $target;
        }
        return $this->mergeInlineRules($inlineRules);
    }
    /**
     * @param array $rules
     * @return bool
     */
    protected function mergeInlineRules($rules)
    {
        foreach ($rules as $mime => $target) {
            $this->inlineRules[$mime] = $target;
        }
        /* Order rules with most specificity first and least specificity last */
        uksort($this->inlineRules, function ($mime1, $mime2)
        {
            return $this->cmpMimePatternSpecificity($mime2, $mime1);
        });
        return true;
    }
    /**
     * Compare MIME patterns by ascending specificity (from least specific to most specific)
     *
     * @param string $mime1
     * @param string $mime2
     * @return int
     */
    protected function cmpMimePatternSpecificity($mime1, $mime2)
    {
        if (($cmp = (self::getMimePatternSpecificity($mime1) - self::getMimePatternSpecificity($mime2))) === 0) {
            return strcmp($mime1, $mime2);
        }
        return $cmp;
    }
    /**
     * Get the specificity of a MIME pattern
     * @param string $pattern
     * @return int Specificity of MIME type: 0 (least specific), 1, or 2 (most specific)
     */
    protected static function getMimePatternSpecificity($pattern)
    {
        $specificity = self::SPECIFICITY_TYPE_AND_SUBTYPE;
        if ($pattern === '*/*') {
            $specificity = self::SPECIFICITY_ANY;
        } elseif (substr($pattern, -2) === '/*') {
            $specificity = self::SPECIFICITY_TYPE_ONLY;
        }
        return $specificity;
    }
    //endregion
    
}

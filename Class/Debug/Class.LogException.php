<?php
/*
 * @author Anakeen
 * @package FDL
*/

namespace Dcp\Core;

class LogException
{
    
    protected static $errId;
    
    public static function getId()
    {
        return self::$errId;
    }
    /**
     * @param \Throwable $e
     *
     * @param string     $errid
     *
     * @return void
     */
    public static function writeLog($e, $errid = null)
    {
        if ($errid === null) {
            $errorId = self::$errId;
        } else {
            $errorId = $errid;
        }
        
        self::writeLogMsg(self::formatErrorLogException($e, $errorId));
    }


    public static function writeLogMsg($msg)
    {
        error_log($msg);
    }

    /**
     * Return Exception message with call stack
     *
     * @param \Throwable $e
     * @param string     $errorId
     *
     * @return string
     */
    public static function formatErrorLogException($e, $errorId = "DCP")
    {
        global $argv;
        
        $pid = getmypid();
        
        $errLine[] = sprintf("[%s] %s> Dynacase got an uncaught exception '%s' with message '%s' in file %s at line %s:", $errorId, $pid, get_class($e) , $e->getMessage() , $e->getFile() , $e->getLine());
        
        $errLine[] = self::getUserInfo();
        
        if (php_sapi_name() == 'cli' && is_array($argv)) {
            $errLine[] = sprintf("%s> Command line arguments: %s", $pid, join(' ', array_map("escapeshellarg", $argv)));
            $errLine[] = sprintf("%s> error_log: %s", $pid, ini_get('error_log'));
        }
        foreach (preg_split('/\n/', $e->getTraceAsString()) as $line) {
            $errLine[] = sprintf("%s> %s", $pid, $line);
        }
        $errLine[] = sprintf("[%s] %s> End Of Exception.", $errorId, $pid);
        return implode("\n", $errLine);
    }
    
    protected static function getUserInfo()
    {
        $u = getCurrentUser();
        if ($u) {
            return sprintf("User : <%s> \"%s %s\" [%d]", $u->login, $u->firstname, $u->lastname, $u->id);
        }
        return null;
    }
    /**
     * Log message according to CORE_DISPLAY_ERROR parameter
     *
     * @param \Exception|array $e Exception or Error info done by error_get_last()
     *
     * @param  string          $errorId
     *
     * @return string
     */
    public static function logMessage($e, &$errorId)
    {
        $msg = self::getMessage($e, $errorId, $logMsg);
        
        if (is_array($e)) {
            self::writeLogMsg(sprintf("[%s] %s", $errorId, $logMsg));
        } else {
            self::writeLogMsg(self::formatErrorLogException($e, $errorId));
        }
        
        return $msg;
    }
    /**
     * Return message according to CORE_DISPLAY_ERROR parameter
     *
     * @param \Exception|array $e
     *
     * @param  string          $errorId    return error identifier to be used in request message and log message
     *
     * @param string           $logMessage complete message
     *
     * @return string anonymous message error if CORE_DISPLAY_ERROR is yes
     */
    public static function getMessage($e, &$errorId, &$logMessage = "")
    {
        global $action;
        $errorId = uniqid("DCP");
        self::$errId = $errorId;
        
        if (is_array($e)) {
            $logMessage = sprintf("%s in %s on line %s", $e["message"], $e["file"], $e["line"]);
            $logMessage.= "\n" . self::getUserInfo();
        } else {
            $logMessage = $e->getMessage();
        }
        
        $displayError = (!empty($action) && $action->getParam("CORE_DISPLAY_ERROR") === "yes");
        
        if (!$displayError) {
            return sprintf("%s.", ___("Whoops, looks like something went wrong", "dcp"));
        } else {
            return $logMessage;
        }
    }
}

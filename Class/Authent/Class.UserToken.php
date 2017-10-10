<?php
/*
 * @author Anakeen
 * @package FDL
*/
/**
 * UserToken class
 *
 * This class provides methods to store and manage authentication
 * tokens with expiration time
 *
 * @author Anakeen
 * @version $Id: Class.UserToken.php,v 1.6 2009/01/16 13:33:00 jerome Exp $
 * @package FDL
 * @subpackage
 */
/**
 */

include_once ('Class.DbObj.php');

class UserToken extends DbObj
{
    var $Class = '$Id: Class.UserToken.php,v 1.6 2009/01/16 13:33:00 jerome Exp $';
    
    var $fields = array(
        'token',
        'type',
        'cdate',
        'authorid',
        'userid',
        'expire',
        'expendable',
        'description',
        'context'
    );
    
    public $token;
    public $userid;
    public $authorid;
    public $expire;
    public $expendable;
    public $context;
    public $cdate;
    public $description;
    public $type = "CORE";
    
    var $id_fields = array(
        'token'
    );
    
    var $dbtable = 'usertoken';
    
    var $sqlcreate = "
    CREATE TABLE usertoken (
      token text NOT NULL PRIMARY KEY,
      type text,
      cdate timestamp without time zone,
      authorid int,
      userid INT NOT NULL,
      expire TIMESTAMP NOT NULL,
      expendable BOOLEAN DEFAULT FALSE,
      description text,
      context text
    );
    CREATE INDEX usertoken_idx ON usertoken(token);
  ";
    
    var $tokenByteLength = 20; // Token size: 160 bits (equal to SHA1 digest output length)
    var $expiration = 86400; // 24 hours
    const INFINITY = "infinity";
    
    public function preInsert()
    {
        if (is_array($this->context)) {
            $this->context = serialize($this->context);
        }
        $this->cdate = date("Y-m-d H:i:s");
        $this->authorid = getCurrentUser()->id;
    }
    
    public function setExpiration($expiration = "")
    {
        if ($expiration == "") {
            $expiration = $this->expiration;
        }
        $this->expire = self::getExpirationDate($expiration);
        
        return $this->expire;
    }
    public static function getExpirationDate($delayInSeconds)
    {
        if (preg_match('/^-?infinity$/', $delayInSeconds)) {
            $expireDate = $delayInSeconds;
        } else {
            if (!is_numeric($delayInSeconds)) {
                return false;
            }
            $expireDate = strftime("%Y-%m-%d %H:%M:%S", time() + $delayInSeconds);
        }
        
        return $expireDate;
    }
    public function genToken()
    {
        $strong = false;
        $bytes = openssl_random_pseudo_bytes($this->tokenByteLength, $strong);
        if ($bytes === false || $strong === false) {
            throw new \Dcp\Exception(sprintf("Unable to get cryptographically strong random bytes from openssl: your system might be broken or too old."));
        }
        return bin2hex($bytes);
    }
    
    public function getToken()
    {
        if ($this->token == "") {
            error_log(__CLASS__ . "::" . __FUNCTION__ . " " . "token is not defined.");
        }
        return $this->token;
    }
    
    public static function deleteExpired()
    {
        $sql = sprintf("DELETE FROM usertoken WHERE expire < now()");
        simpleQuery('', $sql);
    }
    
    public function preUpdate()
    {
        if ($this->token == "") {
            return "Error: token not set";
        }
        if ($this->userid == "") {
            return "Error: userid not set";
        }
        if ($this->expire == "") {
            return "Error: expire not set";
        }
        return '';
    }
}

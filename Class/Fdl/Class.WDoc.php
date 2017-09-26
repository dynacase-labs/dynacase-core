<?php
/*
 * @author Anakeen
 * @package FDL
*/
/**
 * Workflow Class Document
 *
 * @author Anakeen
 * @version $Id: Class.WDoc.php,v 1.63 2009/01/08 17:47:07 eric Exp $
 * @package FDL
 */
/**
 */

include_once ('FDL/Class.Doc.php');
/**
 * WorkFlow Class
 */
class WDoc extends Doc
{
    /**
     * WDoc has its own special access depend on transition
     * by default the three access are always set
     *
     * @var array
     */
    var $acls = array(
        "view",
        "edit",
        "delete"
    );
    
    var $usefor = 'SW';
    var $defDoctype = 'W';
    var $defClassname = 'WDoc';
    var $attrPrefix = "WF"; // prefix attribute
    
    /**
     * state's activities labels
     * @var array
     */
    var $stateactivity = array(); // label of states
    // --------------------------------------------------------------------
    //----------------------  TRANSITION DEFINITION --------------------
    var $transitions = array(); // set by childs classes
    var $cycle = array(); // set by childs classes
    var $autonext = array(); // set by childs classes
    var $firstState = ""; // first state in workflow
    var $viewnext = "list"; // view interface as select list may be (list|button)
    var $nosave = array(); // states where it is not permitted to save and stay (force next state)
    
    /**
     * @var array
     */
    public $states = null;
    /**
     * @var WDoc|null
     */
    private $pdoc = null;
    /**
     * document instance
     * @var Doc
     */
    public $doc = null;
    function __construct($dbaccess = '', $id = '', $res = '', $dbid = 0)
    {
        // first construct acl array
        if (is_array($this->transitions)) {
            foreach ($this->transitions as $k => $trans) {
                $this->extendedAcls[$k] = array(
                    "name" => $k,
                    "description" => _($k)
                );
                $this->acls[] = $k;
            }
        }
        if (isset($this->fromid)) $this->defProfFamId = $this->fromid; // it's a profil itself
        // don't use Doc constructor because it could call this constructor => infinitive loop
        DocCtrl::__construct($dbaccess, $id, $res, $dbid);
    }
    /**
     * affect document instance
     * @param Doc $doc document to use for workflow
     * @param bool $force set to true to force a doc reset
     * @return void
     */
    function set(Doc & $doc, $force = false)
    {
        if ((!isset($this->doc)) || ($this->doc->id != $doc->id) || $force) {
            $this->doc = & $doc;
            if (($doc->doctype != 'C') && ($doc->state == "")) {
                $doc->state = $this->getFirstState();
                $this->changeProfil($doc->state);
                $this->changeCv($doc->state);
            }
        }
    }
    function getFirstState()
    {
        return $this->firstState;
    }
    /**
     * change profil according to state
     * @param string $newstate new state of document
     * @return string
     */
    function changeProfil($newstate)
    {
        $err = '';
        if ($newstate != "") {
            $profid = $this->getRawValue($this->_Aid("_ID", $newstate));
            if (!is_numeric($profid)) $profid = getIdFromName($this->dbaccess, $profid);
            if ($profid > 0) {
                // change only if new profil
                $err = $this->doc->setProfil($profid);
            }
        }
        return $err;
    }
    /**
     * change allocate user according to state
     * @param string $newstate new state of document
     * @return string
     */
    function changeAllocateUser($newstate)
    {
        $err = "";
        if ($newstate != "") {
            $auserref = trim($this->getRawValue($this->_Aid("_AFFECTREF", $newstate)));
            if ($auserref) {
                $uid = $this->getAllocatedUser($newstate);
                $wuid = 0;
                if ($uid) $wuid = $this->getDocValue($uid, "us_whatid");
                if ($wuid > 0) {
                    $lock = (trim($this->getRawValue($this->_Aid("_AFFECTLOCK", $newstate))) == "yes");
                    $err = $this->doc->allocate($wuid, "", false, $lock);
                    if ($err == "") {
                        $automail = (trim($this->getRawValue($this->_Aid("_AFFECTMAIL", $newstate))) == "yes");
                        if ($automail) {
                            include_once ("FDL/mailcard.php");
                            $to = trim($this->getDocValue($uid, "us_mail"));
                            if (!$to) addWarningMsg(sprintf(_("%s has no email address") , $this->getTitle($uid)));
                            else {
                                $subject = sprintf(_("allocation for %s document") , $this->doc->title);
                                $commentaction = '';
                                $err = sendCard($action, $this->doc->id, $to, "", $subject, "", true, $commentaction, "", "", "htmlnotif");
                                if ($err != "") addWarningMsg($err);
                            }
                        }
                    }
                }
            } else $err = $this->doc->unallocate("", false);
        }
        return $err;
    }
    
    private function getAllocatedUser($newstate)
    {
        $auserref = trim($this->getRawValue($this->_Aid("_AFFECTREF", $newstate)));
        $type = trim($this->getRawValue($this->_Aid("_AFFECTTYPE", $newstate)));
        if (!$auserref) return false;
        $aid = strtok($auserref, " ");
        $uid = false;
        switch ($type) {
            case 'F': // fixed address
                //	$wuid=$this->getDocValue($aid,"us_whatid");
                $uid = $aid;
                break;

            case 'PR': // docid parameter
                $uid = $this->doc->getFamilyParameterValue($aid);
                //	if ($uid) $wuid=$this->getDocValue($uid,"us_whatid");
                break;

            case 'WPR': // workflow docid parameter
                $uid = $this->getFamilyParameterValue($aid);
                //	if ($uid) $wuid=$this->getDocValue($uid,"us_whatid");
                break;

            case 'D': // user relations
                $uid = $this->doc->getRValue($aid);
                //	if ($uid)  $wuid=$this->getDocValue($docid,'us_whatid');
                break;

            case 'WD': // user relations
                $uid = $this->getRValue($aid);
                //	if ($uid) $wuid=$this->getDocValue($docid,'us_whatid');
                break;
        }
        return $uid;
    }
    /**
     * change cv according to state
     * @param string $newstate new state of document
     */
    function changeCv($newstate)
    {
        
        if ($newstate != "") {
            $cvid = ($this->getRawValue($this->_Aid("_CVID", $newstate)));
            if (!is_numeric($cvid)) $cvid = getIdFromName($this->dbaccess, $cvid);
            if ($cvid > 0) {
                // change only if set
                $this->doc->cvid = $cvid;
            } else {
                $fdoc = $this->doc->getFamilyDocument();
                $this->doc->cvid = $fdoc->ccvid;
            }
        }
    }
    
    private function _Aid($fix, $state)
    {
        return strtolower($this->attrPrefix . $fix . str_replace(":", "_", $state));
    }
    /**
     * get the profile id according to state
     * @param string $state
     * @return string
     */
    public function getStateProfil($state)
    {
        return $this->getRawValue($this->_Aid("_id", $state));
    }
    /**
     * get the attribute id for profile id according to state
     * @param string $state
     * @return string
     */
    public function getStateProfilAttribute($state)
    {
        return $this->_Aid("_id", $state);
    }
    /**
     * get the mask id according to state
     * @param string $state
     * @return string
     */
    public function getStateMask($state)
    {
        return $this->getRawValue($this->_Aid("_mskid", $state));
    }
    /**
     * get the view control id according to state
     * @param string $state
     * @return string
     */
    public function getStateViewControl($state)
    {
        return $this->getRawValue($this->_Aid("_cvid", $state));
    }
    /**
     * get the timers ids according to state
     * @param string $state
     * @return string
     */
    public function getStateTimers($state)
    {
        return $this->getRawValue($this->_Aid("_tmid", $state));
    }
    /**
     * get the timers ids according to transition
     * @param string $transName transition name
     * @return array
     */
    public function getTransitionTimers($transName)
    {
        return array_merge($this->getMultipleRawValues($this->_Aid("_trans_pa_tmid", $transName)) , $this->getMultipleRawValues($this->_Aid("_trans_tmid", $transName)));
    }
    /**
     * get the mail ids according to transition
     * @param string $transName transition name
     * @return array
     */
    public function getTransitionMailTemplates($transName)
    {
        return $this->getMultipleRawValues($this->_Aid("_trans_mtid", $transName));
    }
    /**
     * get the mail templates ids according to state
     * @param string $state
     * @return array
     */
    public function getStateMailTemplate($state)
    {
        return $this->getMultipleRawValues($this->_Aid("_mtid", $state));
    }
    /**
     * create of parameters attributes of workflow
     * @param int $cid
     * @return string error message
     */
    public function createProfileAttribute($cid = 0)
    {
        if (!$cid) {
            if ($this->doctype == 'C') $cid = $this->id;
            else $cid = $this->fromid;
        }
        $ordered = 1000;
        if (($err = $this->setMasterLock(true)) !== '') {
            return $err;
        }
        // delete old attributes before
        $this->exec_query(sprintf("delete from docattr where docid=%d  and options ~ 'autocreated=yes'", intval($cid)));
        $this->getStates();
        foreach ($this->states as $k => $state) {
            // --------------------------
            // frame
            $aidframe = $this->_Aid("_FR", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aidframe
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = "frame";
            $oattr->id = $aidframe;
            $oattr->frameid = "wf_tab_states";
            $oattr->labeltext = sprintf(_("parameters for %s step") , _($state));
            $oattr->link = "";
            $oattr->phpfunc = "";
            $oattr->options = "autocreated=yes";
            $oattr->ordered = $ordered++;
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // profil id
            $aidprofilid = $this->_Aid("_ID", $state); //strtolower($this->attrPrefix."_ID".strtoupper($state));
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aidprofilid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("PROFIL")';
            $oattr->id = $aidprofilid;
            $oattr->labeltext = sprintf(_("%s profile") , _($state));
            $oattr->link = "";
            $oattr->frameid = $aidframe;
            $oattr->options = "autocreated=yes";
            
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "lprofil(D,CT,WF_FAMID):$aidprofilid,CT";
            $oattr->ordered = $ordered++;
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // mask id
            $aid = $this->_Aid("_MSKID", $state);
            
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("MASK")';
            $oattr->id = $aid;
            $oattr->labeltext = sprintf(_("%s mask") , _($state));
            $oattr->link = "";
            $oattr->frameid = $aidframe;
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "lmask(D,CT,WF_FAMID):$aid,CT";
            $oattr->elink = '';
            $oattr->options = 'autocreated=yes|creation={autoclose:"yes",msk_famid:wf_famid,ba_title:"' . str_replace(':', ' ', _($state)) . '"}';
            $oattr->ordered = $ordered++;
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // state color
            $aid = $this->_Aid("_COLOR", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = "color";
            $oattr->link = "";
            $oattr->phpfile = "";
            $oattr->id = $aid;
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            $oattr->phpfunc = "";
            $oattr->options = "autocreated=yes";
            $oattr->labeltext = sprintf(_("%s color") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // CV link
            $aid = $this->_Aid("_CVID", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("CVDOC")';
            $oattr->link = "";
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "lcvdoc(D,CT,WF_FAMID):$aid,CT";
            $oattr->elink = '';
            $oattr->options = 'autocreated=yes|creation={autoclose:"yes",cv_famid:wf_famid,ba_title:"' . str_replace(':', ' ', _($state)) . '"}';
            $oattr->id = $aid;
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            
            $oattr->labeltext = sprintf(_("%s cv") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // Mail template link
            $aid = $this->_Aid("_MTID", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("MAILTEMPLATE")';
            $oattr->link = "";
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "lmailtemplatedoc(D,CT,WF_FAMID):$aid,CT";
            $oattr->id = $aid;
            $oattr->frameid = $aidframe;
            $oattr->options = "multiple=yes|autocreated=yes";
            
            $oattr->elink = '';
            $oattr->options = 'autocreated=yes|multiple=yes|creation={autoclose:"yes",tmail_family:wf_famid,tmail_workflow:fromid}';
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s mail template") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            //  Timer link
            $aid = $this->_Aid("_TMID", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("TIMER")';
            $oattr->link = "";
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "ltimerdoc(D,CT,WF_FAMID):$aid,CT";
            $oattr->id = $aid;
            $oattr->elink = '';
            $oattr->options = 'autocreated=yes|creation={autoclose:"yes",tm_family:wf_famid,tm_workflow:fromid,tm_title:"' . str_replace(':', ' ', _($state)) . '"}';
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s timer") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            //  Ask link
            $aid = $this->_Aid("_ASKID", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("WASK")';
            $oattr->link = "";
            $oattr->phpfile = "";
            $oattr->phpfunc = "";
            $oattr->id = $aid;
            $oattr->elink = '';
            $oattr->options = 'multiple=yes|autocreated=yes|creation={autoclose:"yes"}';
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s wask") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // Label action
            $aid = $this->_Aid("_ACTIVITYLABEL", $k);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            
            if (!(empty($this->stateactivity[$k]))) {
                $oattr->visibility = "S";
            } else $oattr->visibility = "W";
            $oattr->type = 'text';
            $oattr->link = "";
            $oattr->phpfile = "";
            $oattr->phpfunc = "";
            $oattr->id = $aid;
            $oattr->options = "autocreated=yes";
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            
            $oattr->labeltext = sprintf(_("%s activity") , _($k));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            //  Affected user link
            $aid = $this->_Aid("_T_AFFECT", $state);
            $afaid = $aid;
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "U";
            $oattr->type = 'array';
            $oattr->id = $aid;
            $oattr->frameid = $aidframe;
            $oattr->options = "vlabel=none|autocreated=yes";
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s affectation") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            
            $aid = $this->_Aid("_AFFECTTYPE", $state);
            $aidtype = $aid;
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'enum';
            $oattr->options = "autocreated=yes|system=yes";
            $oattr->phpfunc = "F|" . _("Utilisateur fixe") . ",D|" . _("Attribut relation") . ",PR|" . _("Relation parametre") . ",WD|" . _("Relation cycle") . ",WPR|" . _("Parametre cycle");
            $oattr->id = $aid;
            $oattr->frameid = $afaid;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s affectation type") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            
            $aid = $this->_Aid("_AFFECTREF", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'text';
            $oattr->link = "";
            $oattr->options = "cwidth=160px|autocreated=yes";
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "tpluser(D,$aidtype,WF_FAMID,FROMID,$aid):$aid";
            $oattr->id = $aid;
            $oattr->frameid = $afaid;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s affected user") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            
            $aid = $this->_Aid("_AFFECTLOCK", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'enum';
            $oattr->link = "";
            $oattr->options = "eformat=bool|autocreated=yes|system=yes";
            $oattr->phpfunc = "no|" . _("affect no lock") . ",yes|" . _("affect auto lock");
            $oattr->id = $aid;
            $oattr->frameid = $afaid;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s autolock") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            
            $aid = $this->_Aid("_AFFECTMAIL", $state);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'enum';
            $oattr->link = "";
            $oattr->options = "eformat=bool|autocreated=yes|system=yes";
            $oattr->phpfunc = "no|" . _("affect no mail") . ",yes|" . _("affect auto mail");
            $oattr->id = $aid;
            $oattr->frameid = $afaid;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s automail") , _($state));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
        }
        
        foreach ($this->transitions as $k => $trans) {
            // --------------------------
            // frame
            $aidframe = $this->_Aid("_TRANS_FR", $k);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aidframe
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = "frame";
            $oattr->id = $aidframe;
            $oattr->frameid = "wf_tab_transitions";
            $oattr->labeltext = sprintf(_("parameters for %s transition") , _($k));
            $oattr->link = "";
            $oattr->phpfunc = "";
            $oattr->options = "autocreated=yes";
            $oattr->ordered = $ordered++;
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // Mail template link
            $aid = $this->_Aid("_TRANS_MTID", $k);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("MAILTEMPLATE")';
            $oattr->link = "";
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "lmailtemplatedoc(D,CT,WF_FAMID):$aid,CT";
            $oattr->elink = "";
            $oattr->id = $aid;
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            $oattr->options = 'autocreated=yes|multiple=yes|creation={autoclose:"yes",tmail_family:wf_famid,tmail_workflow:fromid}';
            
            $oattr->labeltext = sprintf(_("%s mail template") , _($k));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // Timer link
            $aid = $this->_Aid("_TRANS_TMID", $k);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("TIMER")';
            $oattr->link = "";
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "ltimerdoc(D,CT,WF_FAMID):$aid,CT";
            $oattr->elink = "";
            $oattr->options = 'autocreated=yes|creation={autoclose:"yes",tm_family:wf_famid,tm_workflow:fromid,tm_title:"' . str_replace(':', ' ', _($k)) . '"}';
            
            $oattr->id = $aid;
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s timer") , _($k));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // Persistent Attach Timer link
            $aid = $this->_Aid("_TRANS_PA_TMID", $k);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("TIMER")';
            $oattr->link = "";
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "ltimerdoc(D,CT,WF_FAMID):$aid,CT";
            $oattr->elink = "";
            $oattr->options = 'multiple=yes|autocreated=yes|creation={autoclose:"yes",tm_family:wf_famid,tm_workflow:fromid,tm_title:"' . str_replace(':', ' ', _($k)) . '"}';
            
            $oattr->id = $aid;
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s persistent timer") , _($k));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
            // --------------------------
            // Persistent UnAttach Timer link
            $aid = $this->_Aid("_TRANS_PU_TMID", $k);
            $oattr = new DocAttr($this->dbaccess, array(
                $cid,
                $aid
            ));
            $oattr->docid = $cid;
            $oattr->visibility = "W";
            $oattr->type = 'docid("TIMER")';
            $oattr->link = "";
            $oattr->phpfile = "fdl.php";
            $oattr->phpfunc = "ltimerdoc(D,CT,WF_FAMID):$aid,CT";
            $oattr->elink = "";
            $oattr->id = $aid;
            $oattr->options = "multiple=yes|autocreated=yes";
            $oattr->frameid = $aidframe;
            $oattr->ordered = $ordered++;
            $oattr->labeltext = sprintf(_("%s unattach timer") , _($k));
            if ($oattr->isAffected()) $oattr->Modify();
            else $oattr->Add();
        }
        if (($err = $this->setMasterLock(false)) !== '') {
            return $err;
        }
        return refreshPhpPgDoc($this->dbaccess, $cid);
    }
    /**
     * change state of a document
     * the method {@link set()} must be call before
     * @param string $newstate the next state
     * @param string $addcomment comment to be set in history (describe why change state)
     * @param bool $force is true when it is the second passage (without interactivity)
     * @param bool $withcontrol set to false if you want to not verify control permission ot transition
     * @param bool $wm1 set to false if you want to not apply m1 methods
     * @param bool $wm2 set to false if you want to not apply m2 methods
     * @param bool $wneed set to false to not test required attributes
     * @param bool $wm0 set to false if you want to not apply m0 methods
     * @param bool $wm3 set to false if you want to not apply m3 methods
     * @param string $msg return message from m2 or m3 methods
     * @return string error message, if no error empty string
     */
    function changeState($newstate, $addcomment = "", $force = false, $withcontrol = true, $wm1 = true, $wm2 = true, $wneed = true, $wm0 = true, $wm3 = true, &$msg = '')
    {
        $err = '';
        // if ($this->doc->state == $newstate) return ""; // no change => no action
        // search if possible change in concordance with transition array
        $foundFrom = false;
        $foundTo = false;
        $tname = '';
        $tr = array();
        foreach ($this->cycle as $trans) {
            if (($this->doc->state == $trans["e1"])) {
                // from state OK
                $foundFrom = true;
                if ($newstate == $trans["e2"]) {
                    $foundTo = true;
                    $tr = $this->transitions[$trans["t"]];
                    $tname = $trans["t"];
                }
            }
        }
        
        if ($this->userid != 1) { // admin can go to any states
            if (!$foundTo) return (sprintf(_("ChangeState :: the new state '%s' is not known or is not allowed from %s") , _($newstate) , _($this->doc->state)));
            if (!$foundFrom) return (sprintf(_("ChangeState :: the initial state '%s' is not known") , _($this->doc->state)));
            if ($this->doc->isLocked()) {
                $lockUserId = abs($this->doc->locked);
                $lockUserAccount = getDocFromUserId($this->dbaccess, $lockUserId);
                if (is_object($lockUserAccount) && $lockUserAccount->isAlive()) {
                    $lockUserTitle = $lockUserAccount->getTitle();
                    if ($lockUserId != $this->userid) {
                        /* The document is locked by another user */
                        if ($this->doc->locked < 0) {
                            /* Currently being edited by another user */
                            return sprintf(_("Could not perform transition because the document is being edited by '%s'") , $lockUserTitle);
                        } else {
                            /* Explicitly locked by another user */
                            return sprintf(_("Could not perform transition because the document is locked by '%s'") , $lockUserTitle);
                        }
                    }
                }
            }
        }
        // verify if privilege granted
        if ($withcontrol) $err = $this->control($tname);
        if ($err != "") return $err;
        /* Set edition mask from view control if a view control is applied on the document */
        $this->doc->setMask(Doc::USEMASKCVEDIT);
        
        if ($wm0 && (!empty($tr["m0"]))) {
            // apply first method (condition for the change)
            if (!method_exists($this, $tr["m0"])) return (sprintf(_("the method '%s' is not known for the object class %s") , $tr["m0"], get_class($this)));
            
            $err = call_user_func(array(
                $this,
                $tr["m0"]
            ) , $newstate, $this->doc->state, $addcomment);
            if ($err != "") {
                $this->doc->unlock(true);
                return (sprintf(_("Error : %s") , $err));
            }
        }
        
        if ($wm1 && (!empty($tr["m1"]))) {
            // apply first method (condition for the change)
            if (!method_exists($this, $tr["m1"])) return (sprintf(_("the method '%s' is not known for the object class %s") , $tr["m1"], get_class($this)));
            
            $err = call_user_func(array(
                $this,
                $tr["m1"]
            ) , $newstate, $this->doc->state, $addcomment);
            
            if ($err == "->") {
                if ($force) {
                    $err = ""; // it is the return of the report
                    SetHttpVar("redirect_app", ""); // override the redirect
                    SetHttpVar("redirect_act", "");
                } else {
                    if ($addcomment != "") $this->doc->addHistoryEntry($addcomment); // add comment now because it will be lost
                    return ""; //it is not a real error, but don't change state (reported)
                    
                }
            }
            if ($err != "") {
                $this->doc->unlock(true);
                return (sprintf(_("Error : %s") , $err));
            }
        }
        // verify if completed doc
        if ($wneed) {
            $err = $this->doc->isCompleteNeeded();
            if ($err != "") return $err;
        }
        // change the state
        $oldstate = $this->doc->state == "" ? " " : $this->doc->state;
        $this->doc->state = $newstate;
        $this->changeProfil($newstate);
        $this->changeCv($newstate);
        $this->doc->disableEditControl();
        $err = $this->doc->Modify(); // don't control edit permission
        if ($err != "") return $err;
        
        $revcomment = sprintf(_("change state : %s to %s") , _($oldstate) , _($newstate));
        if ($addcomment != "") $this->doc->addHistoryEntry($addcomment);
        if (isset($tr["ask"])) {
            foreach ($tr["ask"] as $vpid) {
                $oa = $this->getAttribute($vpid);
                if ($oa->type === "array") {
                    $elem = $this->attributes->getArrayElements($oa->id);
                    foreach ($elem as $aid => $arrayAttribute) {
                        if ($oa->type == "password") {
                            $displayValue = "*****";
                        } else {
                            $displayValue = str_replace("\n", ", ", $this->getRawValue($arrayAttribute->id));
                        }
                        $revcomment.= sprintf("\n-%s : %s", $arrayAttribute->getLabel() , $displayValue);
                    }
                } else {
                    $pv = $this->getRawValue($vpid);
                    if ($pv != "") {
                        
                        if ($oa->type == "password") {
                            $pv = "*****";
                        }
                        
                        if (is_array($pv)) {
                            $pv = implode(", ", $pv);
                        }
                        $revcomment.= sprintf("\n-%s : %s", $oa->getLabel() , $pv);
                    }
                }
            }
        }
        $incumbentName = getCurrentUser()->getIncumbentPrivilege($this, $tname);
        if ($incumbentName) $revcomment = sprintf(_("(substitute of %s) : ") , $incumbentName) . $revcomment;
        $err = $this->doc->revise($revcomment);
        if ($err != "") {
            $this->doc->disableEditControl(); // restore old states
            $this->doc->state = $oldstate;
            $this->changeProfil($oldstate);
            $this->changeCv($oldstate);
            $err2 = $this->doc->Modify(); // don't control edit permission
            $this->doc->enableEditControl();
            
            return $err . $err2;
        }
        AddLogMsg(sprintf(_("%s new state %s") , $this->doc->title, _($newstate)));
        
        $this->doc->enableEditControl();
        // post action
        $msg2 = '';
        if ($wm2 && (!empty($tr["m2"]))) {
            if (!method_exists($this, $tr["m2"])) return (sprintf(_("the method '%s' is not known for the object class %s") , $tr["m2"], get_class($this)));
            $msg2 = call_user_func(array(
                $this,
                $tr["m2"]
            ) , $newstate, $oldstate, $addcomment);
            
            if ($msg2 == "->") $msg2 = ""; //it is not a real error
            if ($msg2) $this->doc->addHistoryEntry($msg2);
            if ($msg2 != "") $msg2 = sprintf(_("Warning : %s") , $msg2);
        }
        $this->doc->addLog("state", array(
            "id" => $this->id,
            "initid" => $this->initid,
            "revision" => $this->revision,
            "title" => $this->title,
            "state" => $this->state,
            "message" => $msg2
        ));
        $this->doc->disableEditControl();
        if (!$this->domainid) $this->doc->unlock(false, true);
        $msg.= $this->workflowSendMailTemplate($newstate, $addcomment, $tname);
        $this->workflowAttachTimer($newstate, $tname);
        $err.= $this->changeAllocateUser($newstate);
        // post action
        $msg3 = '';
        if ($wm3 && (!empty($tr["m3"]))) {
            if (!method_exists($this, $tr["m3"])) return (sprintf(_("the method '%s' is not known for the object class %s") , $tr["m3"], get_class($this)));
            $msg3 = call_user_func(array(
                $this,
                $tr["m3"]
            ) , $newstate, $oldstate, $addcomment);
            
            if ($msg3 == "->") $msg3 = ""; //it is not a real error
            if ($msg3) $this->doc->addHistoryEntry($msg3);
            if ($msg3 != "") $msg3 = sprintf(_("Warning : %s") , $msg3);
        }
        $msg.= ($msg && $msg2 ? "\n" : '') . $msg2;
        if ($msg && $msg3) $msg.= "\n";
        $msg.= $msg3;
        $this->doc->enableEditControl();
        return $err;
    }
    /**
     * return an array of next states availables from current state
     * @param bool $noVerifyDomain set to true if want to get next states when document is locked into a domain
     * @return array
     */
    function getFollowingStates($noVerifyDomain = false)
    {
        // search if following states in concordance with transition array
        if ($this->doc->locked == - 1) return array(); // no next state for revised document
        if (($this->doc->locked > 0) && ($this->doc->locked != $this->doc->userid)) return array(); // no next state if locked by another person
        if ((!$noVerifyDomain) && ($this->doc->lockdomainid > 0)) return array(); // no next state if locked in a domain
        $fstate = array();
        if ($this->doc->state == "") {
            $this->doc->state = $this->getFirstState();
        }
        
        if ($this->userid == 1) {
            return $this->getStates();
        } // only admin can go to any states from anystates
        foreach ($this->cycle as $tr) {
            if ($this->doc->state == $tr["e1"]) {
                // from state OK
                if ($this->control($tr["t"]) == "") $fstate[] = $tr["e2"];
            }
        }
        return $fstate;
    }
    /**
     * return an array of all states availables for the workflow
     * @return array
     */
    function getStates()
    {
        if ($this->states === null) {
            $this->states = array();
            foreach ($this->cycle as $k => $tr) {
                if (!empty($tr["e1"])) $this->states[$tr["e1"]] = $tr["e1"];
                if (!empty($tr["e2"])) $this->states[$tr["e2"]] = $tr["e2"];
            }
        }
        return $this->states;
    }
    /**
     * get associated color of a state
     * @param string $state the state
     * @param string $def default value if not set
     * @return string the color (#RGB)
     */
    function getColor($state, $def = "")
    {
        //$acolor=$this->attrPrefix."_COLOR".($state);
        $acolor = $this->_Aid("_COLOR", $state);
        return $this->getRawValue($acolor, $def);
    }
    /**
     * get activity (localized language)
     * @param string $state the state
     * @param string $def default value if not set
     * @return string the text of action
     */
    function getActivity($state, $def = "")
    {
        //$acolor=$this->attrPrefix."_ACTIVITYLABEL".($state);
        $acolor = $this->_Aid("_ACTIVITYLABEL", $state);
        $v = $this->getRawValue($acolor);
        if ($v) return _($v);
        return $def;
    }
    /**
     * get action (localized language)
     * @deprecated use getActivity instead
     * @param string $state the state
     * @param string $def default value if not set
     * @return string the text of action
     */
    function getAction($state, $def = "")
    {
        deprecatedFunction();
        return $this->getActivity($state, $def);
    }
    /**
     * get askes for a document
     * searcj all WASK document which current user can see for a specific state
     * @param string $state the state
     * @param bool $control set to false to not control ask access
     * @return string[] texts of action
     */
    function getDocumentWasks($state, $control = true)
    {
        $aask = $this->_Aid("_ASKID", $state);
        $vasks = $this->getMultipleRawValues($aask);
        if ($control) {
            $cask = array();
            foreach ($vasks as $askid) {
                /**
                 * @var $ask \Dcp\Family\WASK
                 */
                $ask = new_doc($this->dbaccess, $askid);
                $ask->set($this->doc);
                if ($ask->isAlive() && ($ask->control('answer') == "")) $cask[] = $ask->id;
            }
            return $cask;
        } else {
            return $vasks;
        }
    }
    /**
     * verify if askes are defined
     *
     * @return bool true if at least one ask is set in workflow
     */
    function hasWasks()
    {
        $states = $this->getStates();
        foreach ($states as $state) {
            $aask = $this->_Aid("_ASKID", $state);
            if ($this->getRawValue($aask)) return true;
        }
        return false;
    }
    /**
     * send associated mail of a state
     * @param string $state the state
     * @param string $comment reason of change state
     * @param string $tname transition name
     * @return string
     */
    function workflowSendMailTemplate($state, $comment = "", $tname = "")
    {
        $err = '';
        $tmtid = $this->getMultipleRawValues($this->_Aid("_TRANS_MTID", $tname));
        
        $tr = ($tname) ? $this->transitions[$tname] : null;
        if ($tmtid && (count($tmtid) > 0)) {
            foreach ($tmtid as $mtid) {
                $keys = array();
                /**
                 * @var $mt \Dcp\Family\MAILTEMPLATE
                 */
                $mt = new_doc($this->dbaccess, $mtid);
                if ($mt->isAlive()) {
                    $keys["WCOMMENT"] = nl2br($comment);
                    if (isset($tr["ask"])) {
                        foreach ($tr["ask"] as $vpid) {
                            $keys["V_" . strtoupper($vpid) ] = $this->getHtmlAttrValue($vpid);
                            $keys[strtoupper($vpid) ] = $this->getRawValue($vpid);
                        }
                    }
                    $err.= $mt->sendDocument($this->doc, $keys);
                }
            }
        }
        
        $tmtid = $this->getMultipleRawValues($this->_Aid("_MTID", $state));
        if ($tmtid && (count($tmtid) > 0)) {
            foreach ($tmtid as $mtid) {
                $keys = array();
                $mt = new_doc($this->dbaccess, $mtid);
                /**
                 * @var \Dcp\Family\MAILTEMPLATE $mt
                 */
                if ($mt->isAlive()) {
                    $keys["WCOMMENT"] = nl2br($comment);
                    if (isset($tr["ask"])) {
                        foreach ($tr["ask"] as $vpid) {
                            $keys["V_" . strtoupper($vpid) ] = $this->getHtmlAttrValue($vpid);
                            $keys[strtoupper($vpid) ] = $this->getRawValue($vpid);
                        }
                    }
                    $err.= $mt->sendDocument($this->doc, $keys);
                }
            }
        }
        return $err;
    }
    /**
     * attach timer to a document
     * @param string $state the state
     * @param string $tname transition name
     * @return string
     */
    function workflowAttachTimer($state, $tname = "")
    {
        $err = '';
        $mtid = $this->getRawValue($this->_Aid("_TRANS_TMID", $tname));
        
        $this->doc->unattachAllTimers($this);
        
        if ($mtid) {
            /**
             * @var \Dcp\Family\TIMER $mt
             */
            $mt = new_doc($this->dbaccess, $mtid);
            if ($mt->isAlive()) {
                $err = $this->doc->attachTimer($mt, $this);
            }
        }
        // unattach persistent
        $tmtid = $this->getMultipleRawValues($this->_Aid("_TRANS_PU_TMID", $tname));
        if ($tmtid && (count($tmtid) > 0)) {
            foreach ($tmtid as $mtid) {
                $mt = new_doc($this->dbaccess, $mtid);
                if ($mt->isAlive()) {
                    $err.= $this->doc->unattachTimer($mt);
                }
            }
        }
        
        $mtid = $this->getRawValue($this->_Aid("_TMID", $state));
        if ($mtid) {
            $mt = new_doc($this->dbaccess, $mtid);
            if ($mt->isAlive()) {
                $err.= $this->doc->attachTimer($mt, $this);
            }
        }
        // attach persistent
        $tmtid = $this->getMultipleRawValues($this->_Aid("_TRANS_PA_TMID", $tname));
        if ($tmtid && (count($tmtid) > 0)) {
            foreach ($tmtid as $mtid) {
                $mt = new_doc($this->dbaccess, $mtid);
                if ($mt->isAlive()) {
                    $err.= $this->doc->attachTimer($mt);
                }
            }
        }
        return $err;
    }
    /**
     * to change state of a document from this workflow
     * @param $docid
     * @param $newstate
     * @param string $comment
     * @return string
     */
    function changeStateOfDocid($docid, $newstate, $comment = "")
    {
        $err = '';
        $cmd = new_Doc($this->dbaccess, $docid);
        $cmdid = $cmd->getLatestId(); // get the latest
        $cmd = new_Doc($this->dbaccess, $cmdid);
        
        if ($cmd->wid > 0) {
            /**
             * @var $wdoc Wdoc
             */
            $wdoc = new_Doc($this->dbaccess, $cmd->wid);
            
            if (!$wdoc) $err = sprintf(_("cannot change state of document #%d to %s") , $cmd->wid, $newstate);
            if ($err != "") return $err;
            $wdoc->Set($cmd);
            $err = $wdoc->ChangeState($newstate, sprintf(_("automaticaly by change state of %s\n%s") , $this->doc->title, $comment));
            if ($err != "") return $err;
        }
        return $err;
    }
    /**
     * get transition array for the transition between $to and $from states
     * @param string $to first state
     * @param string $from next state
     * @return array transition array (false if not found)
     */
    function getTransition($from, $to)
    {
        foreach ($this->cycle as $v) {
            if (($v["e1"] == $from) && ($v["e2"] == $to)) {
                $t = $this->transitions[$v["t"]];
                $t["id"] = $v["t"];
                return $t;
            }
        }
        return false;
    }
    /**
     * explicit original doc control
     * @param $aclname
     * @param bool $strict
     * @see Doc::control()
     * @return string
     */
    function docControl($aclname, $strict = false)
    {
        return Doc::Control($aclname, $strict);
    }
    /**
     * Special control in case of dynamic controlled profil
     * @param string $aclname
     * @param bool $strict set to true to not use substitute informations
     * @return string error message
     */
    function control($aclname, $strict = false)
    {
        $err = Doc::control($aclname, $strict);
        if ($err == "") return $err; // normal case
        if ($this->getRawValue("DPDOC_FAMID") > 0) {
            // special control for dynamic users
            if ($this->pdoc === null) {
                $pdoc = createDoc($this->dbaccess, $this->fromid, false);
                $pdoc->doctype = "T"; // temporary
                //	$pdoc->setValue("DPDOC_FAMID",$this->getRawValue("DPDOC_FAMID"));
                $err = $pdoc->Add();
                if ($err != "") return "WDoc::Control:" . $err; // can't create profil
                $pdoc->setProfil($this->profid, $this->doc);
                
                $this->pdoc = & $pdoc;
            }
            $err = $this->pdoc->docControl($aclname, $strict);
        }
        return $err;
    }
    /**
     * affect action label
     */
    function postStore()
    {
        foreach ($this->stateactivity as $k => $v) {
            $this->setValue($this->_Aid("_ACTIVITYLABEL", $k) , $v);
        }
        $this->getStates();
        foreach ($this->states as $k => $state) {
            $allo = trim($this->getRawValue($this->_Aid("_AFFECTREF", $state)));
            if (!$allo) $this->removeArrayRow($this->_Aid("_T_AFFECT", $state) , 0);
        }
        
        if ($this->isChanged()) $this->modify();
    }
    /**
     * get value of instanced document
     * @param string $attrid attribute identifier
     * @param bool $def default value if no value
     * @return string return the value, false if attribute not exist or document not set
     */
    function getInstanceValue($attrid, $def = false)
    {
        if ($this->doc) {
            return $this->doc->getRawValue($attrid, $def);
        }
        return $def;
    }
}

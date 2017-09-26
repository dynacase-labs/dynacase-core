<?php
/*
 * @author Anakeen
 * @package FDL
*/
/**
 * View Document
 *
 * @author Anakeen
 * @version $Id: fdl_card.php,v 1.42 2008/12/02 15:20:52 eric Exp $
 * @package FDL
 * @subpackage
 */
/**
 */

include_once ("FDL/Class.Dir.php");
/**
 * View a document
 * @param Action &$action current action
 * @global string $id Http var : document identifier to see
 * @global string $latest Http var : (Y|N|L|P) if Y force view latest revision, L : latest fixed revision, P : previous revision
 * @global string $state Http var : to view document in latest fixed state (only if revision > 0)
 * @global string $abstract Http var : (Y|N) if Y view only abstract attribute
 * @global string $props Http var : (Y|N) if Y view properties also
 * @global string $zonebodycard Http var : if set, view other specific representation
 * @global string $vid Http var : if set, view represention describe in view control (can be use only if doc has controlled view)
 * @global string $ulink Http var : (Y|N)if N hyperlink are disabled
 * @global string $target Http var : is set target of hyperlink can change (default _self)
 * @global string $inline Http var : (Y|N) set to Y for binary template. View in navigator
 * @global string $reload Http var : (Y|N) if Y update freedom folders in client navigator
 * @global string $dochead Http var :  (Y|N) if N don't see head of document (not title and icon)
 * @global string $unlock Http var : (Y|N) set to Y to unlock the document before viewing (default N)
 */
function fdl_card(&$action)
{
    // -----------------------------------
    $docid = GetHttpVars("id");
    $latest = GetHttpVars("latest");
    $zone = GetHttpVars("zone");
    $ulink = (GetHttpVars("ulink", '2')); // add url link
    $target = GetHttpVars("target"); // may be mail
    $vid = GetHttpVars("vid"); // special controlled view
    $state = GetHttpVars("state"); // search doc in this state
    $inline = (getHttpVars("inline") == "Y"); // view file inline
    $unlock = (getHttpVars("unlock", "N") == "Y");
    
    $dbaccess = $action->dbaccess;
    
    if ($docid == "") $action->exitError(_("no document reference"));
    if (!is_numeric($docid)) $docid = getIdFromName($dbaccess, $docid);
    if (intval($docid) == 0) $action->exitError(sprintf(_("unknow logical reference '%s'") , htmlspecialchars(GetHttpVars("id") , ENT_QUOTES)));
    $doc = new_Doc($dbaccess, $docid);
    if (!$doc->isAffected()) {
        $err = simpleQuery($dbaccess, sprintf("select id from dochisto where id=%d limit 1", $docid) , $hashisto, true, true);
        if ($hashisto) {
            $action->exitError(sprintf(_("Document %s has been destroyed.") , htmlspecialchars($docid, ENT_QUOTES)) . sprintf(" <a href='?app=FDL&action=VIEWDESTROYDOC&id=%s'>%s</a>", urlencode($docid) , htmlspecialchars(_("See latest information about it.") , ENT_QUOTES)) , true);
        } else {
            $action->exitError(htmlspecialchars(sprintf(_("cannot see unknow reference %s") , $docid)) , ENT_QUOTES);
        }
    }
    
    if ($unlock) {
        $err = $doc->UnLock(true);
        if ($err != "") $action->ExitError($err);
    }
    
    fixMultipleAliveDocument($doc);
    if ($state != "") {
        $docid = $doc->getRevisionState($state, true);
        if ($docid == 0) {
            $action->exitError(sprintf(_("Document %s in %s state not found") , htmlspecialchars($doc->title, ENT_QUOTES) , htmlspecialchars(_($state) , ENT_QUOTES)));
        }
        SetHttpVar("id", $docid);
    } else {
        if (($latest == "Y") && ($doc->locked == - 1)) {
            // get latest revision
            $docid = $doc->getLatestId();
            if ($docid == "") $action->exitError(_("no alive document reference"));
            SetHttpVar("id", $docid);
        } else if (($latest == "L") && ($doc->lmodify != 'L')) {
            // get latest fixed revision
            $docid = $doc->getLatestId(true);
            SetHttpVar("id", $docid);
        } else if (($latest == "P") && ($doc->revision > 0)) {
            // get previous fixed revision
            $pdoc = getRevTDoc($dbaccess, $doc->initid, $doc->revision - 1);
            $docid = $pdoc["id"];
            SetHttpVar("id", $docid);
        }
    }
    
    if ($docid != $doc->id) $doc = new_doc($dbaccess, $docid);
    
    SetHttpVar("viewbarmenu", 1);
    
    $action->parent->addCssRef("css/dcp/main.css");
    $action->parent->addCssRef("css/dcp/document-view.css");
    $mgeo = $action->getParam("MVIEW_GEO");
    $action->lay->set("mviewgeo", $mgeo);
    
    $action->lay->set("RSS", ($doc->getRawValue("gui_isrss") === "yes"));
    if ($action->lay->get("RSS")) {
        $action->lay->set("rsslink", $doc->getRssLink());
    }
    $action->lay->Set("TITLE", $doc->getHtmlTitle());
    $action->lay->Set("id", $doc->id);
    $action->lay->Set("initid", $doc->initid);
    if ($action->read("navigator") == "EXPLORER") $action->lay->Set("shorticon", getParam("DYNACASE_FAVICO"));
    else $action->lay->Set("shorticon", $doc->getIcon());
    $action->lay->Set("docicon", $doc->getIcon('', 16));
    $action->lay->Set("pds", $doc->urlWhatEncodeSpec(""));
    
    $tview = array();
    if (($zone == "") && ($vid != "")) {
        /**
         * @var CVDoc $cvdoc
         */
        $cvdoc = new_Doc($dbaccess, $doc->cvid);
        if ($cvdoc->fromid == 28) {
            $cvdoc->set($doc);
            
            $err = $cvdoc->control(trim($vid)); // control special view
            if ($err != "") $action->exitError("CV:" . htmlspecialchars($cvdoc->title, ENT_QUOTES) . "\n" . $err);
            $tview = $cvdoc->getView($vid);
            $zone = $tview["CV_ZVIEW"];
        }
    }
    if ($zone == "") $zone = $doc->defaultview;
    $zo = $doc->getZoneOption($zone);
    if ($zo === "S") { // waiting for special zone contradiction
        $action->lay->template = $doc->viewdoc($zone, $target, $ulink);
    } else {
        $engine = $doc->getZoneTransform($zone);
        if ($engine) {
            $sgets = fdl_getGetVars();
            redirect($action, "FDL", "GETFILETRANSFORMATION&idv=$vid&zone=$zone$sgets&id=" . $doc->id, $action->GetParam("CORE_STANDURL"));
            exit;
        }
        if ($zo == "B") {
            // binary layout file
            if (!empty($tview["CV_MSKID"])) {
                $err = $doc->setMask($tview["CV_MSKID"]);
                if ($err) addWarningMsg($err);
            }
            $file = $doc->viewdoc($zone, $target, $ulink);
            if ((!file_exists($file))) { // error in  file generation
                $action->lay->template = $file;
                return;
            }
            
            $ext = getFileExtension($file);
            if ($ext == '') $ext = "html";
            $mime = getSysMimeFile($file, basename($file));
            //	print "$file,".$doc->title.".$ext $mime"; exit;
            Http_DownloadFile($file, $doc->title . ".$ext", $mime, $inline, false);
            @unlink($file);
            exit;
        } else {
            $action->lay->set("nocss", ($zo == "U"));
            $taction = array();
            if ($doc->doctype != 'C' && $doc->doctype != 'Z') {
                $listattr = $doc->GetActionAttributes();
                $mwidth = $action->getParam("FDL_HD2SIZE", 300);
                $mheight = $action->getParam("FDL_VD2SIZE", 400);
                foreach ($listattr as $k => $v) {
                    if (($v->mvisibility != "H") && ($v->mvisibility != "O")) {
                        if ($v->getOption("onlymenu") != "yes") {
                            $mvis = MENU_ACTIVE;
                            if ($v->precond != "") $mvis = $doc->ApplyMethod($v->precond, MENU_ACTIVE);
                            if ($mvis == MENU_ACTIVE) {
                                $taction[$k] = array(
                                    "wadesc" => $v->getOption("llabel") ,
                                    "walabel" => ucfirst($v->getLabel()) ,
                                    "wwidth" => $v->getOption("mwidth", $mwidth) ,
                                    "wheight" => $v->getOption("mheight", $mheight) ,
                                    "wtarget" => ($v->getOption("ltarget") == "") ? (($v->getOption("mtarget") == "") ? $v->id . "_" . $doc->id : $v->getOption("mtarget")) : $v->getOption("ltarget") ,
                                    "wlink" => $doc->urlWhatEncode($v->getLink($doc->getLatestId()))
                                );
                            }
                        }
                    }
                }
            }
            $action->lay->setBlockData("WACTION", $taction);
            $action->lay->set("VALTERN", ($action->GetParam("FDL_VIEWALTERN", "yes") == "yes"));
        }
    }
}

function fdl_getGetVars()
{
    global $_GET;
    
    $exclude = array(
        "app",
        "action",
        "sole",
        "id",
        "zone"
    );
    $s = '';
    foreach ($_GET as $k => $v) {
        if (!in_array($k, $exclude)) $s.= "&$k=$v";
    }
    
    return $s;
}

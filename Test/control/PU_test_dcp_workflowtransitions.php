<?php
/*
 * @author Anakeen
 * @package FDL
*/

namespace Dcp\Pu;
/**
 * @author Anakeen
 * @package Dcp\Pu
 */

require_once 'PU_testcase_dcp_commonfamily.php';

class TestWorflowTransition extends TestCaseDcpCommonFamily
{
    /**
     * import TST_DEFAULTFAMILY1 family
     * @static
     * @return string
     */
    protected static function getCommonImportFile()
    {
        return "PU_data_dcp_impworkflowfamilym0m3.ods";
    }
    /**
     * @dataProvider dataTransitionCondition
     * @param $start
     * @param $end
     * @param $passExpectedError
     */
    public function testTransitionCondition($famId, $start, $end, $passExpectedError, $customStore = null, $customSetState = null)
    {
        $wf = new_doc(self::$dbaccess, "WTST_M0M3");
        $this->assertTrue($wf->isAlive() , "cannot find document WTST_M0M3");
        
        $d = createDoc(self::$dbaccess, $famId);
        $d->setTitle('zou');
        $d->wid = $wf->id;
        $d->state = $start;
        if (is_callable($customStore)) {
            $err = $customStore($d);
        } else {
            $err = $d->store();
        }
        $this->assertEmpty($err, "cannot create ${famId} document: ${err}");
        
        if (is_callable($customSetState)) {
            $err = $customSetState($d, $end);
        } else {
            $err = $d->setState($end);
        }
        if ($passExpectedError == '') {
            $this->assertEmpty($err, sprintf("transition %s -> %s is not passed : %s", $start, $end, $err));
        } else {
            
            $this->assertContains($passExpectedError, $err, sprintf("transition %s -> %s is passed and must not", $start, $end));
        }
        if (isset(self::$user)) {
            $this->exitSudo();
        }
    }
    /**
     * @dataProvider dataTransitionPostAction
     * @param $start
     * @param $end
     * @param $passExpectedMsg
     */
    public function testTransitionPostAction($famId, $start, $end, $passExpectedMsg)
    {
        $wf = new_doc(self::$dbaccess, "WTST_M0M3");
        $this->assertTrue($wf->isAlive() , "cannot find document WTST_M0M3");
        
        $d = createDoc(self::$dbaccess, $famId);
        $d->setTitle('zou');
        $d->wid = $wf->id;
        $d->state = $start;
        $err = $d->store();
        $this->assertEmpty($err, "cannot create ${famId} document");
        
        $err = $d->setState($end, '', false, true, true, true, true, true, true, $msg);
        $this->assertEmpty($err, sprintf("transition %s -> %s is not passed : %s", $start, $end, $err));
        
        if ($passExpectedMsg == '') {
            $this->assertEmpty($err, sprintf("transition %s -> %s is not passed : %s", $start, $end, $err));
        } else {
            $this->assertContains($passExpectedMsg, $msg, sprintf("transition %s -> %s is passed and must not", $start, $end));
        }
    }
    public function dataTransitionCondition()
    {
        return array(
            array(
                'TST_WFFAMM0M3',
                \WTestM0M3::SA,
                \WTestM0M3::SC,
                ''
            ) ,
            array(
                'TST_WFFAMM0M3',
                \WTestM0M3::SA,
                \WTestM0M3::SB,
                'm0 forbidden'
            ) ,
            array(
                'TST_WFFAMM0M3',
                \WTestM0M3::SA,
                \WTestM0M3::SD,
                ''
            ) ,
            array(
                'TST_WFFAMM0M3',
                \WTestM0M3::SB,
                \WTestM0M3::SC,
                'm1 forbidden'
            ) ,
            array(
                'TST_WFFAMM0M3',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                ''
            ) ,
            array(
                'TST_WFFAM_DEV_7061',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                ''
            ) ,
            array(
                'TST_WFFAM_DEV_7061',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                'Texte colonne 1 doit être rempli',
                function (\Dcp\Family\Document & $doc)
                {
                    $doc->setVAlue('TXT_COLTEXT_1', array(
                        // Missing/empty TST_COLTEXT_1 altogether
                        
                    ));
                    $doc->setValue('TST_COLTEXT_2', array(
                        'Row 1',
                        'Row 2'
                    ));
                    return $doc->store();
                }
            ) ,
            array(
                'TST_WFFAM_DEV_7061',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                'Texte colonne 1 doit être rempli',
                function (\Dcp\Family\Document & $doc)
                {
                    $doc->setValue('TXT_COLTEXT_1', array(
                        '', // First cell is empty
                        'Row 2'
                    ));
                    $doc->setValue('TST_COLTEXT_2', array(
                        'Row 1',
                        'Row 2'
                    ));
                    return $doc->store();
                }
            ) ,
            array(
                'TST_WFFAM_DEV_7061',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                'Texte colonne 1 doit être rempli',
                function (\Dcp\Family\Document & $doc)
                {
                    $doc->setValue('TXT_COLTEXT_1', array(
                        'Row 1',
                        ''
                        // Last cell is empty
                        
                    ));
                    $doc->setValue('TST_COLTEXT_2', array(
                        'Row 1',
                        'Row 2'
                    ));
                    return $doc->store();
                }
            ) ,
            /*
             * Locked documents
            */
            array(
                /*
                 * Locked by myself: transition is allowed
                */
                'TST_WFFAM_DEV_7062',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                '',
                function (\Dcp\Family\Document & $doc)
                {
                    /*
                     * Lock document with User #1
                    */
                    /** @var \Dcp\Family\Iuser $user1 */
                    $user1 = new_Doc('', 'DEV_6072_U1');
                    $userId1 = $user1->getRawValue('us_whatid');
                    $err = array();
                    $err[] = $doc->store();
                    $err[] = $doc->lock(true, $userId1);
                    $err[] = $doc->store();
                    return join("\n", array_filter($err, function ($elmt)
                    {
                        return strlen($elmt) > 0;
                    }));
                }
                ,
                function (\Dcp\Family\Document & $doc, $newState)
                {
                    /*
                     * Change state with User #1
                    */
                    $this->sudo('dev_6072_u1');
                    /* Re-fetch document from database */
                    $doc = new_Doc(self::$dbaccess, $doc->id);
                    $err = $doc->setState($newState);
                    return $err;
                }
            ) ,
            array(
                /*
                 * Document being modified by another user: transition not allowed
                */
                'TST_WFFAM_DEV_7062',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                sprintf(_("Could not perform transition because the document is being edited by '%s'") , 'User #1') ,
                function (\Dcp\Family\Document & $doc)
                {
                    /*
                     * (auto)Lock document with User #1
                    */
                    /** @var \Dcp\Family\Iuser $user1 */
                    $user1 = new_Doc('', 'DEV_6072_U1');
                    $userId1 = $user1->getRawValue('us_whatid');
                    $err = array();
                    $err[] = $doc->store();
                    $err[] = $doc->lock(true, $userId1);
                    $err[] = $doc->store();
                    return join("\n", array_filter($err, function ($elmt)
                    {
                        return strlen($elmt) > 0;
                    }));
                }
                ,
                function (\Dcp\Family\Document & $doc, $newState)
                {
                    /*
                     * Change state with User #2
                    */
                    $this->sudo('dev_6072_u2');
                    /* Re-fetch document from database */
                    $doc = new_Doc(self::$dbaccess, $doc->id);
                    $err = $doc->setState($newState);
                    return $err;
                }
            ) ,
            array(
                /*
                 * Document explicitly locked by another user: transition not allowed
                */
                'TST_WFFAM_DEV_7062',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                sprintf(_("Could not perform transition because the document is locked by '%s'") , 'User #1') ,
                function (\Dcp\Family\Document & $doc)
                {
                    /*
                     * Lock document with User #1
                    */
                    /** @var \Dcp\Family\Iuser $user1 */
                    $user1 = new_Doc('', 'DEV_6072_U1');
                    $userId1 = $user1->getRawValue('us_whatid');
                    $err = array();
                    $err[] = $doc->store();
                    $err[] = $doc->lock(false, $userId1);
                    $err[] = $doc->store();
                    return join("\n", array_filter($err, function ($elmt)
                    {
                        return strlen($elmt) > 0;
                    }));
                }
                ,
                function (\Dcp\Family\Document & $doc, $newState)
                {
                    /*
                     * Change state with User #2
                    */
                    $this->sudo('dev_6072_u2');
                    /* Re-fetch document from database */
                    $doc = new_Doc(self::$dbaccess, $doc->id);
                    $err = $doc->setState($newState);
                    return $err;
                }
            )
        );
    }
    
    public function dataTransitionPostAction()
    {
        return array(
            array(
                'TST_WFFAMM0M3',
                \WTestM0M3::SA,
                \WTestM0M3::SC,
                ''
            ) ,
            array(
                'TST_WFFAMM0M3',
                \WTestM0M3::SA,
                \WTestM0M3::SD,
                'm3 pass'
            ) ,
            array(
                'TST_WFFAMM0M3',
                \WTestM0M3::SB,
                \WTestM0M3::SD,
                'm2 pass'
            )
        );
    }
}

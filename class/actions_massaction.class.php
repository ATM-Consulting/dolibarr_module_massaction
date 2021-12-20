<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_massaction.class.php
 * \ingroup massaction
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/mailings/thirdparties.modules.php';

require_once DOL_DOCUMENT_ROOT.'/core/modules/mailings/modules_mailings.php';
require_once DOL_DOCUMENT_ROOT.'/comm/mailing/class/mailing.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/emailing.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/massaction/lib/massaction.lib.php';


/**
 * Class Actionsmassaction
 */
class Actionsmassaction
{
    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * Constructor
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

	public function doPreMassActions($parameters, &$object, &$action, $hookmanager){

		global $massaction, $langs;

		$TContext = explode(":", $parameters['context']);

		if(in_array('thirdpartylist', $TContext)
			|| in_array('contactlist', $TContext)
			|| in_array('memberlist', $TContext)
			|| in_array('userlist', $TContext)) {

			$toprint = '';

			//Selection du mailing concerné
			if ($massaction == 'linktomailing') {

				$TMailings = array();
				$_SESSION['toselect'] = $parameters['toselect'];


				$toprint .= dol_get_fiche_head(null, '', '');

				//selection de tous les mailings au statut brouillon, soit 0
				$sql = "SELECT rowid, titre";
				$sql .= " FROM ".MAIN_DB_PREFIX."mailing";
				$sql .= " WHERE statut = 0";

				$resql = $this->db->query($sql);

				if($resql){
					while($obj = $this->db->fetch_object($resql)){
						$TMailings[$obj->rowid] = $obj->titre;
					}
				}

				//définition du form de confirmation
				$formquestion = array();

				$formquestion[]=array('type' => 'select',
					'name' => 'select_mailings',
					'label' => '',
					'select_show_empty' => 0,
					'values' => $TMailings);

				$form = new Form($this->db);
				$toprint .= $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans("MassActionSelectEmailing"), $langs->trans("ConfirmSelectEmailing"), "confirm_linktomailing", $formquestion, 1, 0, 200, 500, 1);

				$toprint .= dol_get_fiche_end();

				$this->resprints = $toprint;
			} elseif ($massaction == 'linksalesperson'){

				$user = new User($this->db);
				$res = $user->fetchAll('', '', 0, 0, array('t.statut' => 1));
				$TUsers = array();
				if($res) {
					foreach($user->users as $user){
						$TUsers[$user->id] = $user->firstname . ' ' . $user->lastname . ' (' . $user->login . ')';
					}

				}

				if(!empty($TUsers)){

					$toprint .= dol_get_fiche_head(null, '', '');

					//définition du form de confirmation
					$formquestion = array();

					$formquestion[]=array('type' => 'select',
						'name' => 'select_salesperson',
						'label' => '',
						'select_show_empty' => 0,
						'values' => $TUsers);

					$formquestion[]=array('type' => 'select',
						'name' => 'select_salesperson_option',
						'label' => $langs->trans('MassActionSalesPersonAction'),
						'select_show_empty' => 0,
						'values' => array(0=>$langs->trans('Add'), 1=>$langs->trans('MassActionReplace')));

					$form = new Form($this->db);
					$toprint .= $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans("MassActionSelectSalesPerson"), $langs->trans("ConfirmSelectSalesPerson"), "confirm_linksalesperson", $formquestion, 1, 0, 200, 500, 1);

					$toprint .= dol_get_fiche_end();

					$this->resprints = $toprint;

				}
			}
		}

	}

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          &$action        Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doMassActions($parameters, &$object, &$action, $hookmanager)
    {
        require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
        global $conf, $user, $langs, $db, $massaction, $diroutputmassaction;

		if(empty($massaction) && GETPOSTISSET('massaction')) $massaction = GETPOST('massaction', 'alphanohtml');

		$TContext = explode(":", $parameters['context']);

		$error = 0; // Error counter

        //print_r($parameters); echo "action: " . $action;
        if (strpos($parameters['context'], 'list') !== 0)
        {
            $langs->load('massaction@massaction');

            // @TODO Ask for compression format and filename
            /*if($massaction == 'generate_zip')
            {
                $form = new Form($db);
                $formquestion = array(
                    array('type' => 'other','name' => 'compression_mode','label' => $langs->trans('MassActionGenerateZIPOptionsText'),'value' => $this->selectCompression())
                );
                $text = $langs->trans('MassActionGenerateZIPOptionsText');
                $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?massaction=confirm_generate_zip', $langs->trans('MassActionGenerateZIPOptions'), $text, 'confirm_generate_zip', $formquestion, "yes", 2);
                $this->resprints = $formconfirm;
            }*/

            if($massaction == 'generate_zip')
            {
                if (empty($diroutputmassaction) || empty($parameters['uploaddir']))
                {
                    $error++;
                    $errormsg = $langs->trans('NoDirectoryAvailable');
                }

                if(!$error) {
                    // Lists all file to add in the zip archive
                    $formfile = new FormFile($db);
                    $toarchive = array();
                    foreach ($parameters['toselect'] as $objectid) {
                        $object->fetch($objectid);
                        $ref = dol_sanitizeFileName($object->ref);
                        if($object->element == 'invoice_supplier') $subdir = get_exdir($object->id, 2, 0, 0, $object, 'invoice_supplier').$ref;
                        else $subdir = $ref;

                        $filedir = $parameters['uploaddir'] . '/' . $subdir;

                        // @TODO : use dol_dir_list ?
                        /*
                        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
                        $filearray=dol_dir_list($filedir,"files",1,'','','',1);
                        */

                        $formfile->getDocumentsLink($object->element, $ref, $filedir);
                        $toarchive = array_merge($toarchive, $formfile->infofiles['files']);
                    }

                    if (empty($toarchive)) {
                        $error++;
                        $errormsg = $langs->trans('NoFileToAddInArchive');
                    }
                }

                if(!$error) {
                    $compressmode = GETPOST('compress_mode', 'alphanohtml');
                    $compressmode = 'zip';
                    //$filename = GETPOST('filename');
                    $filename = 'dolibarr_'.date('Ymd_His').'.'.$compressmode;

                    // Copy files in a temp directory
                    $tempdir = $diroutputmassaction.'/temp/';
                    dol_mkdir($tempdir);
                    foreach ($toarchive as $filepath) {
                        dol_copy($filepath, $tempdir.'/'.basename($filepath), 0, 1);
                    }

                    // @TODO : deal with other compression type than zip
                    // Generate the zip archive
                    $file = new SplFileInfo(($tempdir));
                    $tempdir = $file->getRealPath();
                    dol_compress_dir($tempdir, $diroutputmassaction.'/'.$filename, $compressmode);
                    // Delete temp directory
                    dol_delete_dir_recursive($tempdir);
                    // Auto Download
                    if (file_exists($diroutputmassaction.'/'.$filename)) {
                        if (isset($conf->global->MASSACTION_AUTO_DOWNLOAD)){
                            header('Content-Type: application/zip');
                            header('Content-Disposition: attachment; filename="'.$filename.'"');
                            header('Content-Length: ' . filesize($diroutputmassaction.'/'.$filename));
                            readfile($diroutputmassaction.'/'.$filename);
                        }
                        else {
                            setEventMessage($langs->trans('MassActionZIPGenerated', count($toarchive)));
                        }
                    }
                    else{
                        setEventMessage($langs->trans('MassActionErrorGeneration'),'errors');
                    }
                    header('Location:'.$_SERVER['PHP_SELF']);
                }
            }
        }


		if(in_array('thirdpartylist', $TContext)
			|| in_array('contactlist', $TContext)
			|| in_array('memberlist', $TContext)
			|| in_array('userlist', $TContext)) {

			$confirm = GETPOST('confirm', 'alphanohtml');
			$mailing_selected = GETPOST('select_mailings', 'int');

			if($action == 'confirm_linktomailing' && $confirm == 'yes'){

				if(!empty($mailing_selected)){

					//on rassemble les informations dans un tableau "$TCibles" afin d'ajouter au mailing les nouveaux destinataires
					$TCibles = array();
					if($object->element == "member")  $obj = new Adherent($this->db);
					else $obj = new $object->element($this->db);

					if(!empty($_SESSION['toselect'])){
						foreach($_SESSION['toselect'] as $toselect) {
							$res = $obj->fetch($toselect);
							if ($res) {
								$TCibles[$obj->id]['id'] = $obj->id;
								$TCibles[$obj->id]['email'] = $obj->email;
								$TCibles[$obj->id]['lastname'] = (!empty($obj->lastname)) ? $obj->lastname : $obj->name;
								if (!empty($obj->firstname)) $TCibles[$obj->id]['firstname'] = $obj->firstname;
								$TCibles[$obj->id]['source_url'] = getUrlToMailingCibles($object->element, $obj);
							}
						}
					}

					//on ajoute les destinataires au mailing préalablement sélectionné
					$mailingtargets = new MailingTargets($this->db);
					$nbtargetadded = $mailingtargets->addTargetsToDatabase($mailing_selected,$TCibles);

					if($nbtargetadded < 0) {			//erreur
						$error++;
						$this->errors[] = $langs->trans("MassActionTargetsError");
					} else {

						$mailing= new Mailing($this->db);
						$res = $mailing->fetch($mailing_selected);

						if($res >0) {
							$url_mailing = $mailing->getNomURL(0);			//lien du mailing concerné
							setEventMessage($langs->trans('MassActionNbRecipientsAdded', $nbtargetadded) . ' ' . $url_mailing);
						} else {
							setEventMessage($langs->trans('MassActionNbRecipientsAdded', $nbtargetadded) . ' ' . $mailing_selected);
						}
					}
				}
			} elseif ($action == 'confirm_linksalesperson' && $confirm == 'yes'){

				$toselect = GETPOST('toselect', 'array');
				$salesperson_id = GETPOST('select_salesperson', 'int');
				$salesperson_option = GETPOST('select_salesperson_option', 'int');

				$societe = new Societe($this->db);

				foreach($toselect as $thirdparty_id){

					$res = $societe->fetch($thirdparty_id);
					if($res){
						$res = $societe->setSalesRep($salesperson_id, ($salesperson_option == 0) ? true : false);
						if($res < 0) $error++;
						else {
							$url_societe = $societe->getNomURL(0);			//lien du mailing concerné
							setEventMessage($langs->trans('MassActionLinkSalesPersonSuccess') . ' : ' . $url_societe);
						}
					} else {
						$error++;
					}

				}


			}

		}

        if (! $error) {
			/*$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';*/
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = $errormsg;
            return -1;
        }
    }

    /**
     * Overloading the addMoreMassActions function : replacing the parent's function with the one below
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;
        $langs->load('massaction@massaction');

        $error = 0; // Error counter

		$TContext = explode(":", $parameters['context']);

        //print_r($parameters); print_r($object); echo "action: " . $action;
        if (strpos($parameters['context'], 'list') !== 0)		// do something only for the context 'somecontext1' or 'somecontext2'
        {
            $langs->load('massaction@massaction');
            $disabled = false;
            $this->resprints = '<option value="generate_zip"'.($disabled?' disabled="disabled"':'').'>'.$langs->trans("MassActionGenerateZIP").'</option>';
            //$disabled = false;
            //$this->resprints.= '<option value="generate_pdf"'.($disabled?' disabled="disabled"':'').'>'.$langs->trans("MassActionGeneratePDF").'</option>';
        }

        if (in_array('supplierinvoicelist', $TContext))
		{
			$disabled = false;
			$this->resprints .= '<option value="presend"'.($disabled?' disabled="disabled"':'').'>'.$langs->trans("SendByMail").'</option>';
		}

		if(in_array('thirdpartylist', $TContext)
			|| in_array('contactlist', $TContext)
			|| in_array('memberlist', $TContext)
			|| in_array('userlist', $TContext)) {

			if ($conf->mailing->enabled) {
				//options "Mailing : ajouter destinataires"
				$label = '<span class="fa fa-envelope-o" style=""></span> ' . $langs->trans("MassActionLinktoMailing");
				$this->resprints .= '<option value="linktomailing"' . ($disabled ? ' disabled="disabled"' : '') . ' data-html="' . dol_escape_htmltag($label) . '">' . $label . '</option>';

			}
		}
		if(in_array('thirdpartylist', $TContext)){

			if ($conf->societe->enabled) {
				//options "Mailing : ajouter destinataires"
				$label = '<span class="fa fa-user" style=""></span> ' . $langs->trans("MassActionLinkSalesperson");
				$this->resprints .= '<option value="linksalesperson"' . ($disabled ? ' disabled="disabled"' : '') . ' data-html="' . dol_escape_htmltag($label) . '">' . $label . '</option>';

			}
		}
		if (! $error) {
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    function selectCompression() {
        global $langs;

        $compression['gz'] = array('function' => 'gzopen', 'id' => 'compression_gzip', 'label' => $langs->trans("Gzip"));
        $compression['zip']= array('function' => 'dol_compress_dir', 'id' => 'compression_zip',  'label' => $langs->trans("FormatZip"));
        $compression['bz'] = array('function' => 'bzopen',       'id' => 'compression_bzip', 'label' => $langs->trans("Bzip2"));

        $select = '<select name="compression_mode">';
        foreach($compression as $key => $val)
        {
            if (! $val['function'] || function_exists($val['function']))	// Enabled export format
            {
                $select.= '<option value="'.$val['id'].'">'.$val['label'].'</option>';
            }
            else	// Disabled export format
            {
                $select.= '<option value="'.$val['id'].'" disabled>'.$val['label'].'</option>';
            }
        }
        $select.= '</select>';

        return $select;
    }
}

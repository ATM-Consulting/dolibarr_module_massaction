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
require_once __DIR__.'/../lib/massaction.lib.php';


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

	public function doPreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $massaction, $langs;

		$TContext = explode(":", $parameters['context']);
		$toprint = '';

		// Action en masse d'ajout de destinataires à un e-mailing, choix de l'e-mailing
		if($massaction == 'linktomailing' && $conf->mailing->enabled && $user->rights->mailing->creer
			&& (in_array('thirdpartylist', $TContext)
			|| in_array('contactlist', $TContext)
			|| in_array('memberlist', $TContext)
			|| in_array('userlist', $TContext)))
		{
			//Selection du mailing concerné
			$TMailings = array();

			// Récupération de tous les mailings au statut brouillon (0)
			$sql = "SELECT rowid, titre";
			$sql.= " FROM " . MAIN_DB_PREFIX . "mailing";
			$sql.= " WHERE statut = 0";

			$resql = $this->db->query($sql);

			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$TMailings[$obj->rowid] = $obj->rowid.' - '.$obj->titre;
				}
			}

			// Question pour le choix de l'e-mailing
			$formquestion[] = array(
				'type' => 'select',
				'name' => 'select_mailings',
				'label' => $langs->trans('MassActionSelectEmailing'),
				'select_show_empty' => 0,
				'values' => $TMailings
			);

			$form = new Form($this->db);
			$toprint = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans("MassActionLinktoMailing"), $langs->trans("ConfirmSelectEmailing"), "confirm_linktomailing", $formquestion, 0, 0, 200, 500, 1);
		}

		// Action en masse d'affectation de commerciaux aux tiers, choix des options
		if($massaction == 'linksalesperson' && $user->rights->societe->creer
			&& in_array('thirdpartylist', $TContext))
		{
			$form = new Form($this->db);
			$userList = $form->select_dolusers('', 'select_salesperson', 1, '', 0, '', '', 0, 0, 0, '', 0, '', '', 0, 0, 1);

			// Question pour le choix des utilisateurs
			$formquestion[]=array(
				'type' => 'other',
				'name' => 'select_salesperson',
				'label' => $langs->trans("MassActionSelectSalesPerson"),
				'value' => $userList
			);

			// Question pour le choix de l'option d'affectation
			$formquestion[]=array(
				'type' => 'select',
				'name' => 'select_salesperson_option',
				'label' => $form->textwithpicto($langs->trans('MassActionSalesPersonAction'), $langs->trans('MassActionSalesPersonHelp'), 1, 'help', '', 0, 2, ''),
				'select_show_empty' => 0,
				'values' => array(0=>$langs->trans('Add'), 1=>$langs->trans('MassActionReplace'), 2 =>$langs->trans('MassActionDelete'))
			);

			$toprint = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans("MassActionLinkSalesperson"), $langs->trans("ConfirmSelectSalesPerson"), "confirm_linksalesperson", $formquestion, 'no', 0, 200, 500, 1);
		}

		$this->resprints = $toprint;
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
        global $conf, $user, $langs, $db, $massaction, $diroutputmassaction;
		$langs->load('massaction@massaction');

		if(empty($massaction) && GETPOSTISSET('massaction')) $massaction = GETPOST('massaction', 'alphanohtml');

		$TContext = explode(":", $parameters['context']);
		$confirm = GETPOST('confirm', 'alphanohtml');

		$error = 0; // Error counter
		$errormsg = ''; // Error message

		// Action en masse "Génération archive zip"
        if ($massaction == 'generate_zip' && strpos($parameters['context'], 'list') !== 0)
        {
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


			if (empty($diroutputmassaction) || empty($parameters['uploaddir']))
			{
				$error++;
				$errormsg = $langs->trans('NoDirectoryAvailable');
			}

			if(!$error) {
				// Lists all file to add in the zip archive
				require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
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
				//$compressmode = GETPOST('compress_mode', 'alphanohtml');
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
				exit;
			}
        }

		// Action en masse d'ajout de destinataires à un e-mailing
		if ($action == 'confirm_linktomailing' && $confirm == 'yes' && $conf->mailing->enabled && $user->rights->mailing->creer &&
			(in_array('thirdpartylist', $TContext)
			|| in_array('contactlist', $TContext)
			|| in_array('memberlist', $TContext)
			|| in_array('userlist', $TContext)))
		{
			$mailing_selected = GETPOST('select_mailings', 'int');
			$toselect = GETPOST('toselect', 'array');

			if (!empty($mailing_selected)) {
				// On rassemble les informations dans un tableau "$TCibles" afin d'ajouter au mailing les nouveaux destinataires
				$TCibles = array();
				if ($object->element == "member") $obj = new Adherent($this->db);
				else $obj = new $object->element($this->db);

				if (!empty($toselect)) {
					foreach ($toselect as $element_id) {
						$res = $obj->fetch($element_id);
						if ($res && !empty($obj->email)) {
							$TCibles[$obj->id]['id'] = $obj->id;
							$TCibles[$obj->id]['email'] = $obj->email;
							$TCibles[$obj->id]['lastname'] = (!empty($obj->lastname)) ? $obj->lastname : $obj->name;
							if (!empty($obj->firstname)) $TCibles[$obj->id]['firstname'] = $obj->firstname;
							$TCibles[$obj->id]['source_url'] = getUrlToMailingCibles($object->element, $obj);
						}
					}
				}

				// On ajoute les destinataires au mailing préalablement sélectionné
				$mailingtargets = new MailingTargets($this->db);
				$nbtargetadded = $mailingtargets->addTargetsToDatabase($mailing_selected, $TCibles);

				if ($nbtargetadded < 0) {            //erreur
					$error++;
					$errormsg = "MassActionTargetsError";
				} else {
					$mailing = new Mailing($this->db);
					$res = $mailing->fetch($mailing_selected);

					if ($res > 0) {
						$url_mailing = $mailing->getNomURL(0);
						setEventMessage($langs->trans('MassActionNbRecipientsAdded', $nbtargetadded) . ' ' . $url_mailing);
					} else {
						setEventMessage($langs->trans('MassActionNbRecipientsAdded', $nbtargetadded) . ' ' . $mailing_selected);
					}
				}
			}
		}

		// Action en masse d'affectation de commercial
		if ($action == 'confirm_linksalesperson' && $confirm == 'yes' && $user->rights->societe->creer &&
			in_array('thirdpartylist', $TContext))
		{
			$toselect = GETPOST('toselect', 'array');
			$select_salesperson = GETPOST('select_salesperson', 'array');
			// Option : 0 = ajout, 1 = remplacement, 2 = retrait
			$salesperson_option = GETPOST('select_salesperson_option', 'int');

			$societe = new Societe($this->db);

			foreach($toselect as $thirdparty_id) {
				$res = $societe->fetch($thirdparty_id);
				if($res) {
					if($salesperson_option == 2) {
						foreach($select_salesperson as $id_salesperson) {
							$res = $societe->del_commercial($user, $id_salesperson);
							if($res < 0) {
								$error++;
								$errormsg = $societe->error;
							}
						}
					} else {
						$res = $societe->setSalesRep($select_salesperson, ($salesperson_option == 0));
						if($res < 0) {
							$error++;
							$errormsg = $societe->error;
						}
					}
				} else {
					$error++;
					$errormsg = $societe->error;
				}
			}

			if(!$error) {
				setEventMessage($langs->trans('MassActionLinkSalesPersonSuccess'));
			}
		}

        if (! $error) {
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

		// Ajout de l'action en masse "Génération archive zip" sur les listes
        if (strpos($parameters['context'], 'list') !== 0)
        {
			$label = '<span class="fa fa-file-archive paddingrightonly"></span> ' . $langs->trans("MassActionGenerateZIP");
			$this->resprints = '<option value="generate_zip" data-html="' . dol_escape_htmltag($label) . '">' . $label . '</option>';
        }

		// Ajout de l'action en masse "Envoi par e-mail" sur la liste des factures fournisseur
        if (in_array('supplierinvoicelist', $TContext))
		{
			$this->resprints .= '<option value="presend">'.$langs->trans("SendByMail").'</option>';
		}

		// Ajout de l'action en masse d'ajout de destinataires à un e-mailing
		if(in_array('thirdpartylist', $TContext)
			|| in_array('contactlist', $TContext)
			|| in_array('memberlist', $TContext)
			|| in_array('userlist', $TContext)) {

			if ($conf->mailing->enabled && $user->rights->mailing->creer) {
				$label = '<span class="fa fa-envelope-o paddingrightonly"></span> ' . $langs->trans("MassActionLinktoMailing");
				$this->resprints .= '<option value="linktomailing" data-html="' . dol_escape_htmltag($label) . '">' . $label . '</option>';
			}
		}

		// Ajout de l'action en masse d'affectation de commerciaux aux tiers
		if(in_array('thirdpartylist', $TContext)){
			if ($user->rights->societe->creer) {
				$label = '<span class="fa fa-user paddingrightonly"></span> ' . $langs->trans("MassActionLinkSalesperson");
				$this->resprints .= '<option value="linksalesperson" data-html="' . dol_escape_htmltag($label) . '">' . $label . '</option>';
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

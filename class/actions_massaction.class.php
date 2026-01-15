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
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/mailing/class/mailing.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/emailing.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once __DIR__.'/../lib/massaction.lib.php';
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/supplier_proposal/class/supplier_proposal.class.php';



/**
 * Class Actionsmassaction
 */
class Actionsmassaction extends \massaction\RetroCompatCommonHookActions
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
		if($massaction == 'linktomailing' && isModEnabled('mailing') && $user->hasRight('mailing', 'creer')
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
		if($massaction == 'linksalesperson' && $user->hasRight('societe', 'creer')
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
	 * Overloading the doMassActions function : replacing the parent's function with the one below
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
		$massActionToken = GETPOST('massaction_token', 'alphanohtml');

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
					if (getDolGlobalString('MASSACTION_AUTO_DOWNLOAD')){
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
		if ($action == 'confirm_linktomailing' && $confirm == 'yes' && isModEnabled('mailing') && $user->hasRight('mailing', 'creer') &&
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
		if ($action == 'confirm_linksalesperson' && $confirm == 'yes' && $user->hasRight('societe', 'creer') &&
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
		if (strpos($parameters['context'], 'list') !== false)
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

			if (isModEnabled('mailing') && $user->hasRight('mailing', 'creer')) {
				$label = '<span class="fa fa-envelope-o paddingrightonly"></span> ' . $langs->trans("MassActionLinktoMailing");
				$this->resprints .= '<option value="linktomailing" data-html="' . dol_escape_htmltag($label) . '">' . $label . '</option>';
			}
		}

		// Ajout de l'action en masse d'affectation de commerciaux aux tiers
		if(in_array('thirdpartylist', $TContext)){
			if ($user->hasRight('societe', 'creer')) {
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


	/**
	 * @param $parameters
	 * @param $object
	 * @param $action
	 * @param $hookmanager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager) : int {
		global $langs, $user, $conf;

		$TContexts = explode(':', $parameters['context']);
		$TAllowedContexts = ['propalcard', 'ordercard', 'invoicecard'];

		$commonContexts = array_intersect($TContexts, $TAllowedContexts);

		if (!empty($commonContexts)) {
			require_once __DIR__ . '/massaction.class.php';
			if (empty($massActionToken)) {
				$massActionToken = MassAction::getMassActionToken();
			}

			$langs->load('massaction@massaction');
			$massAction = new MassAction($this->db, $object);

			$selectedLines = GETPOST('selectedLines', 'alpha');
			$TSelectedLines = explode(',', $selectedLines);

			$confirm = GETPOST('confirm', 'alpha');

			if ($action == 'preSelectSupplierPrice' && !$confirm && !GETPOST('sendit', 'alpha') && !GETPOST('remove_massaction_file', 'alpha')) {
				MassAction::cleanupTemporaryUploads(array(), '', $massActionToken);
			}

			if ($action == 'delete_lines' && $confirm == 'yes') {
				$TRowIds = array_column($object->lines, 'rowid');

				$this->db->begin();

				foreach ($TSelectedLines as $selectedLine) {
					$index = array_search(intval($selectedLine), $TRowIds);
					$massAction->deleteLine($index, $selectedLine);
				}

				if(!empty($massAction->TErrors)) {
					$this->db->rollback();
				} else {
					$this->db->commit();
				}

				$massAction->handleErrors($TSelectedLines, $massAction->TErrors, $action);

				$action = '';

			}

			if (($action == 'edit_quantity' || $action == 'edit_margin') && $confirm == 'yes') {
				$TRowIds = array_column($object->lines, 'rowid');

				$quantity = null;
				$marge_tx = null;

				if ($action == 'edit_quantity') {
					$quantity = GETPOST('quantity', 'alpha');
					$errors = MassAction::checkFields($action, $quantity);
				} elseif ($action == 'edit_margin') {
					$marge_tx = GETPOST('marge_tx', 'alpha');
					$errors = MassAction::checkFields($action, $marge_tx);
				}

				$this->db->begin();

				if (empty($errors)) {
					foreach ($TSelectedLines as $selectedLine) {
						$index = array_search(intval($selectedLine), $TRowIds);

						$massAction->updateLine($index, $quantity, $marge_tx);

					}
				}

				if(!empty($massAction->TErrors)) {
					$this->db->rollback();
				} else {
					$this->db->commit();
				}

				$massAction->handleErrors($TSelectedLines, $errors, $action);

				$action = '';

			}
			// Supplier price request management
			if ($action == 'preSelectSupplierPrice' && GETPOST('sendit', 'alpha')) {
				$uploadResult = MassAction::persistUploadedFiles($_FILES['massaction_files'] ?? array(), $massActionToken);
				if (!empty($uploadResult['errors'])) {
					setEventMessages($langs->trans('ErrorFileNotUploaded'), $uploadResult['errors'], 'errors');
				} elseif (!empty($uploadResult['files'])) {
					setEventMessage($langs->trans('FileTransmitted'));
				}
				$action = 'preSelectSupplierPrice';
			} elseif ($action == 'preSelectSupplierPrice' && GETPOST('remove_massaction_file', 'alpha')) {
				$fileToRemove = GETPOST('remove_massaction_file', 'alpha');
				$resultRemove = MassAction::removePersistedUpload($fileToRemove, $massActionToken);
				if ($resultRemove < 0) {
					setEventMessage($langs->trans('ErrorFailedToDeleteFile', $fileToRemove), 'errors');
				} else {
					setEventMessage($langs->trans('FileDeleted'));
				}
				$action = 'preSelectSupplierPrice';
			}
			if ($action == 'createSupplierPrice') {
				if ($confirm !== 'yes') {
					MassAction::cleanupTemporaryUploads(array(), '', $massActionToken);
					$action = '';
					return 0;
				}

				$supplierIds = GETPOST('supplierid', 'array');
				$templateId = GETPOST('model_mail', 'int');
				$deliveryDate = null;
				if (GETPOSTINT('maxresponse_year')) {
					$deliveryDate = dol_mktime(12, 0, 0, GETPOSTINT('maxresponse_month'), GETPOSTINT('maxresponse_day'), GETPOSTINT('maxresponse_year'));
				}
				// Attachments are already persisted during preSelectSupplierPrice
				$massAction->handleCreateSupplierPriceAction($object, $TSelectedLines, $supplierIds, (int) $templateId, $deliveryDate, array(), $massActionToken);
			}
		}
		return 0;
	}


	/**
	 * @param $parameters
	 * @param Propal|Facture|Commande $object
	 * @param $action
	 * @param $hookmanager
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $action, $user;
		$TContexts = explode(':', $parameters['context']);

		$TAllowedContexts = ['propalcard', 'ordercard', 'invoicecard'];

		$commonContexts = array_intersect($TContexts, $TAllowedContexts);

		if (!empty($commonContexts)) {
			require_once __DIR__ . '/massaction.class.php';

			$langs->load('massaction@massaction');

			$selectedLines = GETPOST('selectedLines', 'alpha');
			$TSelectedLines = explode(',', $selectedLines);
			$id = $object->id;
			$form = new Form($this->db);

			if (in_array('propalcard', $TContexts)) {
				$permissionToAdd = $user->hasRight('propal', 'creer'); // Perms for propal
			} elseif (in_array('ordercard', $TContexts)) {
				$permissionToAdd = $user->hasRight('order', 'creer'); // Perms for order
			} elseif (in_array('invoicecard', $TContexts)) {
				$permissionToAdd = $user->hasRight('invoice', 'creer'); // Perms for invoice
			}

			$status = $object->status;
			$enableCheckboxes = 0;

			if(
				(
					($object->element == 'propal' && $status == Propal::STATUS_DRAFT) ||
					($object->element == 'facture' && $status == Facture::STATUS_DRAFT) ||
					($object->element == 'commande' && $status == Commande::STATUS_DRAFT))
				&& $permissionToAdd
			) {
				$massActionButton = MassAction::getMassActionButton($form);
				$enableCheckboxes = 1;
			}

			$formConfirm = MassAction::getFormConfirm($action, $TSelectedLines, $id, $form);

			?>

			<script type="text/javascript">

				var massActionButton = <?php echo json_encode($massActionButton); ?>;

				var enableCheckboxes = <?php echo $enableCheckboxes; ?>;

				var formConfirm = <?php echo json_encode($formConfirm) ?>;

				function showCheckboxes() {
					if (enableCheckboxes) {
						var count = 0;

						// Pour chaque tr, je veux une checkbox dans le td sauf si ce n'est pas une ligne (par exemple le form d'ajout en bas)
						$('#tablelines tbody tr').each(function () {
							var rowId = $(this).attr('id');
							if (rowId && rowId.startsWith("row-")) {
								count++;
								var dataId = $(this).data('id');
								$(this).append('<td class="nowrap" align="center"><input id="cb' + dataId + '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' + dataId + '"></td>');
							} else if (!$(this).find('td:first').is('[colspan="100%"]')) { // Gestion avec sous-total car il ajoute un td colspan 100%
								$(this).append('<td></td>');
							}
						});

						// Ajout de la checkbox "générale" pour sélectionner toute les lignes d'un coup
						if (count > 0) {
							$('#tablelines .liste_titre').append(`
							<th class="center">
								<div class="inline-block checkallactions">
									<input type="checkbox" id="checkforselects" name="checkforselects" class="checkallactions">
								</div>
							</th>
						`);
						}
					}
				}

				// Cette fonction met à jour l'input hidden selectedLines pour ajouter les lignes sélectionnées séparées par virgules
				function updateSelectedLines() {
					var TSelectedLines = [];
					$('.checkforselect:checked').each(function () {
						TSelectedLines.push($(this).val());
					})
					$('#selectedLines').val(TSelectedLines.join(','));
				}

				$(document).ready(function () {

					// Reset toutes les checkbox
					$('input[type="checkbox"].checkforselect').prop('checked', false);

					var action = "<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $id, ENT_QUOTES, 'UTF-8'); ?>";

					var token = "<?php echo newToken() ?>";

					var selectedLines = "<?php echo $selectedLines ?>";
					var TSelectedLines = selectedLines.split(',');

					var currentAction = "<?php echo $action ?>";
					var toShow = formConfirm !== '' ? formConfirm : (massActionButton || '');

					var form = `
						<form method="post" id="massactionForm" action="` + action + `">
							<input type="hidden" name="token" value="` + token + `">
							<input type="hidden" name="selectedLines" id="selectedLines" value="` + selectedLines + `">
							<input type="hidden" name="action" value="">

							` + toShow + `

						</form>
					`;

					$('#addproduct:last-child').before(form);

					showCheckboxes();

					if (currentAction === 'preSelectSupplierPrice') {
						$('#massactionForm').attr('enctype', 'multipart/form-data');
						$('.massaction-remove-file').on('click', function (e) {
							e.preventDefault();
							var fname = $(this).data('filename');
							$('.removedfilehidden').val(fname);
							$('input[name="action"]').val('preSelectSupplierPrice');
							$('#confirm').val('no');
							$('#massactionForm').submit();
						});
						$('.massaction-file-input').on('change', function () {
							if ($(this).val()) {
								$('input[name="action"]').val('preSelectSupplierPrice');
								$('#confirm').val('no');
								$('input[name="sendit"]').val('1');
								$('#massactionForm').submit();
							}
						});
						$('.confirmvalidatebutton').on('click', function () {
							$('input[name="action"]').val('createSupplierPrice');
						});
					}

					// Cocher automatiquement les cases à cocher si l'action est predelete ou edit_margin ou edit_quantity
					if (currentAction === 'predelete' || currentAction === 'preeditquantity' || currentAction === 'preeditmargin' || currentAction === 'preSelectSupplierPrice') {
						$(".checkforselect").each(function () {
							var checkboxValue = $(this).val();
							if (TSelectedLines.includes(checkboxValue)) {
								$(this).prop('checked', true);
							}
						});
						updateSelectedLines();  // Mettre à jour les lignes sélectionnées
					}

					// Sélection de toutes les lignes si checkforselects est checked
					$('div.checkallactions #checkforselects').click(function () {
						if ($(this).is(':checked')) {
							console.log("We check all checkforselect and trigger the change method");
							$(".checkforselect").prop('checked', 'true').trigger('change');
						} else {
							console.log("We uncheck all");
							$(".checkforselect").prop('checked', false).trigger('change');
						}
						if (typeof initCheckForSelect == 'function') {
							initCheckForSelect(0, "massaction", "checkforselect")
						} else {
							console.log("No function initCheckForSelect found. Call won't be done.")
						}
					})

					// Highlight des lignes sélectionnées
					$('.checkforselect').change(function () {
						$(this).closest('tr').toggleClass('highlight', this.checked);
						updateSelectedLines();
					});

					$(".massactionselect").change(function () {
						var massaction = $(this).val();
						$('input[name="action"]').val(massaction);
					});
				})
			</script>

			<?php

		}

		return 0;
	}



	/** Overloading the doActions function : replacing the parent's function with the one below
	 *  @param      parameters  meta datas of the hook (context, etc...)
	 *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 *  @param      action             current action (if set). Generally create or edit or null
	 *  @return       void
	 */

	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs,$db,$user, $conf, $mc;

		$langs->load('massaction@massaction');

		$TContexts = explode(':',$parameters['context']);

		if(in_array('ordercard', $TContexts) || in_array('propalcard', $TContexts) || in_array('invoicecard', $TContexts)) {

			$rightCreate = method_exists($user,'hasRight') ? $user->hasRight($object->element,'create') : $user->rights->{$object->element}->creer;
			$rightWrite = method_exists($user,'hasRight') ? $user->hasRight($object->element,'write') : $user->rights->{$object->element}->write;

			$displayButton = ($object->statut == 0 && ($rightCreate || $rightWrite));

			if($action == 'cut') {
				$selectedLines = GETPOST('selectedLines', 'alpha');
			}

			$TActions = [
				'actionSplitDelete' => 'SplitDeleteOk',
				'actionSplit' => 'SplitOk',
				'actionSplitCopy' => 'SplitCopyOk',
				'actionCreateSupplierPrice' => 'CreateSupplierPriceOk',
			];

			foreach ($TActions as $key => $message) {
				$value = GETPOST($key, 'alpha');
				if ($value == 'ok') {
					$url = GETPOST('new_url', 'alpha');
					$messageParam = !empty($url) ? "- $url" : "";
					setEventMessage($langs->trans($message, $messageParam));
					break;
				}
			}

			if ($displayButton) {
				if($object->element=='facture') $idvar = 'facid';
				else $idvar = 'id';
				if($object->element == 'propal') {
					$fiche = '/comm/propal/card.php';
				} else if($object->element == 'commande') {
					$fiche = '/commande/card.php';
				} else if($object->element == 'facture') {
					$fiche = '/compta/facture/card.php';
				}

				$token = function_exists('newToken')?newToken():$_SESSION['newtoken'];
				?><script type="text/javascript">

					var currentAction = "<?php echo $action ?>";
					$(document).ready(function() {
						if(currentAction == "cut") {
							$('#pop-split').remove();
							$('body').append('<div id="pop-split"></div>');

							$.get('<?php echo dol_buildpath('/massaction/script/showLines.php',1).'?id='.$object->id.'&element='.$object->element.'&selectedLines='.$selectedLines ?>', function(data) {
								$('#pop-split').html(data)

								$('#pop-split').dialog({
									title:'<?php echo $langs->transnoentities('SplitThisDocument') ?>'
									,width:'80%'
									,modal: true
									,buttons: [
										{
											text: "<?php echo $langs->transnoentities('SimplyCopy'); ?>",
											title: "<?php echo $langs->transnoentities('SimplyCopyTitle'); ?>",
											click: function() {
												$('#splitform input[name=action]').val('copy');
												$.ajax({
													url: '<?php echo dol_buildpath('/massaction/script/splitLines.php', 1); ?>'
													, method: 'POST'
													, data: $('#splitform').serialize()
													,dataType: "json"
													// La fonction à apeller si la requête aboutie
													,success: function (data) {
														//console.log(data);
														// Loading data
														if(data.result > 0){
															document.location.href = "<?php echo dol_buildpath($fiche, 1).'?id='.$object->id; ?>&token=" + data.newToken;
														}
														else{
															if(data.errorMessage.length > 0){
																$.jnotify(data.errorMessage, 'error', {timeout: 5, type: 'error', css: 'error'});
															}else{
																$.jnotify('UnknowError', 'error', {timeout: 5, type: 'error', css: 'error'});
															}
														}
													}
													// La fonction à appeler si la requête n'a pas abouti
													,error: function( jqXHR, textStatus ) {
														$.jnotify("Request failed: " + textStatus , 'error', {timeout: 5, type: 'error', css: 'error'});
													}
												});

												$( this ).dialog( "close" );
											}
										},
										{
											text: "<?php echo $langs->transnoentities('SplitIt'); ?>",
											title: "<?php echo $langs->transnoentities('SplitItTitle'); ?>",
											click: function() {
												$.ajax({
													url: '<?php echo dol_buildpath('/massaction/script/splitLines.php', 1); ?>'
													, method: 'POST'
													, data: $('#splitform').serialize()
													,dataType: "json"
													// La fonction à apeller si la requête aboutie
													,success: function (data) {
														// Loading data
														//console.log(data);
														if(data.result > 0){
															document.location.href = "<?php echo dol_buildpath($fiche, 1).'?id='.$object->id; ?>&token=" + data.newToken;
														}
														else{
															if(data.errorMessage.length > 0){
																$.jnotify(data.errorMessage, 'error', {timeout: 5, type: 'error', css: 'error'});
															}else{
																$.jnotify('UnknowError', 'error', {timeout: 5, type: 'error', css: 'error'});
															}
														}
													}
													// La fonction à appeler si la requête n'a pas abouti
													,error: function( jqXHR, textStatus ) {
														$.jnotify("Request failed: " + textStatus , 'error', {timeout: 5, type: 'error', css: 'error'});
													}
												});

												$( this ).dialog( "close" );
											}
										}

									]
								});
							});
						}
					});

				</script><?php
			}
		}
		return 0;
	}


}

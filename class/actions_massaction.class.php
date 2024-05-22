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
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';



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
		if($massaction == 'linktomailing' && $conf->mailing->enabled && $user->hasRight('mailing', 'creer')
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
		if ($action == 'confirm_linktomailing' && $confirm == 'yes' && $conf->mailing->enabled && $user->hasRight('mailing', 'creer') &&
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

			if ($conf->mailing->enabled && $user->hasRight('mailing', 'creer')) {
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
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
//	public function printObjectLineTitle($parameters, &$object, &$action, $hookmanager)
//	{
//		$TContexts = explode(':', $parameters['context']);
//
//		if(in_array('propalcard', $TContexts)) {
//

//<!---->
//<!--			<script type="text/javascript">-->
//<!--				$(document).ready(function () {-->
//<!--					$('.liste_titre').append('<th class="linecolcheck"><input type="checkbox"></th>');-->
//<!--				})-->
//<!--			</script>-->
//<!---->
//<!--
//
//		}
//
//		return 0;
//	}
//
//
//	public function printObjectLine($parameters, &$object, &$action, $hookmanager)
//	{
//		$TContexts = explode(':', $parameters['context']);
//
//		if(in_array('propalcard', $TContexts)) {
//
//
//<!--			<script type="text/javascript">-->
//<!--				console.log("Rows found: ", $('#tablelines tbody tr').length);-->
//<!---->
//<!--				$('tbody tr').each(function() {-->
//<!--					var rowId = $(this).data('id'); // Assurez-vous que chaque <tr> a un attribut data-id correct-->
//<!--					$(this).append('<td class="nowrap" align="center"><input id="cb' + rowId + '" type="checkbox" name="toselect[]" value="' + rowId + '"></td>');-->
//<!--				});-->
//<!--			</script>-->
//<!---->
//<!--			-->
//
//		}
//
//		return 0;
//	}


	public function doActions($parameters, &$object, &$action, $hookmanager)
	{

		global $langs;

		$form = new Form($this->db);

		$TContexts = explode(':', $parameters['context']);

		if(in_array('propalcard', $TContexts)) {

			$TSelectedLines = GETPOST('selectedLines', 'alpha');

			$confirm = GETPOST('confirm');

			if ($action == 'delete_lines' && $confirm == 'yes') {

				foreach (explode(',', $TSelectedLines) as $selectedLine) {

					$object->deleteline(intval($selectedLine));

				}

			}

		}

		return 0;
	}

	public function beforeBodyClose($parameters)
	{
		global $langs, $action;


		$form = new Form($this->db);

		$TContexts = explode(':', $parameters['context']);

		$selectedLines = GETPOST('selectedLines', 'alpha');

		$massaction = GETPOST('massaction', 'alpha');

		if(in_array('propalcard', $TContexts)) {
			$arrayofmassactions = array();

			$permissiontosendbymail = 1;
			$permissiontovalidate = 1;
			$permissiontoclose = 1;
			$permissiontodelete = 1;

			$arrayofmassactions['setbilled'] =img_picto('', 'bill', 'class="pictofixedwidth"').$langs->trans("ClassifyBilled");
			$arrayofmassactions['cut']=img_picto('', 'fa-scissors', 'class="pictofixedwidth"').$langs->trans("Cut");

			if ($permissiontodelete) {
				$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
			}

			$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

			if($massaction == 'predelete') {
				$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans("ConfirmMassValidation"), $langs->trans("ConfirmMassValidationQuestion"), "delete_lines", null, '', 0, 200, 500, 1);
			}

			$id = GETPOSTINT('id');

			?>

			<script type="text/javascript">

				var massActionButton = <?php echo json_encode($massactionbutton); ?>;

				var formConfirm = <?php echo json_encode($formconfirm) ?>;

				function initCheckForSelect(mode, name, cssclass)	/* mode is 0 during init of page or click all, 1 when we click on 1 checkboxi, "name" refers to the class of the massaction button, "cssclass" to the class of the checkfor select boxes */
				{
					atleastoneselected=0;
					jQuery("."+cssclass).each(function( index ) {
						if ($(this).is(':checked')) atleastoneselected++;
					});

					if (atleastoneselected > 0) {
						$('.massactionform').removeClass('hideobject')
					} else {
						$('.massactionform').addClass('hideobject')
					}
				}

				function showCheckboxes() {
					var count = 0;
					// Ajout de la checkbox "générale" pour sélectionner toute les lignes d'un coup
					if(count > 0){
						$('#tablelines .liste_titre').append(`
							<th class="center">
								<div class="inline-block checkallactions">
									<input type="checkbox" id="checkforselects" name="checkforselects" class="checkallactions">
								</div>
							</th>
						`);
					}

					// Pour chaque tr, je veux une checkbox dans le td sauf si ce n'est pas une ligne propale
					$('#tablelines tbody tr').each(function () {
						var rowId = $(this).attr('id');
						if (rowId && rowId.startsWith("row-")) {
							count++;
							var dataId = $(this).data('id');
							$(this).append('<td class="nowrap" align="center"><input id="cb' + dataId + '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' + dataId + '"></td>');
						} else {
							$(this).append('<td></td>');
						}
					});
				}

				// Cette fonction met à jour l'input hidden selectedLines pour ajouter les lignes sélectionnées séparées par virgules
				function updateSelectedLines() {
					var TSelectedLines = [];
					$('.checkforselect:checked').each(function() {
						TSelectedLines.push($(this).val());
					})
					$('#selectedLines').val(TSelectedLines.join(','));
				}

				$(document).ready(function () {

					// Reset toutes les checkbox
					$('input[type="checkbox"]').prop('checked', false);

					var action = "<?php echo htmlspecialchars($_SERVER['PHP_SELF']. '?id=' .$id, ENT_QUOTES, 'UTF-8'); ?>";

					var token = "<?php echo newToken() ?>";

					var selectedLines = "<?php echo $selectedLines ?>";

					if (formConfirm != null) {
						toShow = formConfirm;
					} else {
						toShow = massActionButton;
					}

					var form = `
						<form method="POST" id="searchFormList" action="` + action + `">
							<input type="hidden" name="token" value="` + token + `">
							<input type="hidden" name="selectedLines" id="selectedLines" value="`+selectedLines+`">
							<input type="hidden" name="action" value="">

							`+toShow+`

						</form>
					`;

					$('#addproduct:last-child').before(form);

					showCheckboxes();

					// Sélection de toutes les lignes si checkforselects est checked
					$('div.checkallactions #checkforselects').click(function (){
						if($(this).is(':checked')) {
							console.log("We check all checkforselect and trigger the change method");
							$(".checkforselect").prop('checked', 'true').trigger('change');
						} else {
							console.log("We uncheck all");
							$(".checkforselect").prop('checked', false).trigger('change');
						}
						if(typeof initCheckForSelect == 'function') {
							initCheckForSelect(0, "massaction", "checkforselect")
						} else {
							console.log("No function initCheckForSelect found. Call won't be done.")
						}
					})

					// Highlight des lignes sélectionnées
					$('.checkforselect').change(function() {
						$(this).closest('tr').toggleClass('highlight', this.checked);
						updateSelectedLines();
					})

					// Massaction
					initCheckForSelect(0, "massaction", "checkforselect");

					$(".checkforselect").click(function() {
						initCheckForSelect(1, "massaction", "checkforselect");
					});


					$(".massactionselect").change(function() {
						var massaction = $( this ).val();

						$('input[name="action"]').val(massaction);

						/* Warning: if you set submit button to disabled, post using Enter will no more work if there is no other button */
						if ($(this).val() != '0')
						{
							jQuery(".massactionconfirmed").prop('disabled', false);
							jQuery(".massactionother").hide();	/* To disable if another div was open */
							jQuery(".massaction"+massaction).show();
						}
						else
						{
							jQuery(".massactionconfirmed").prop('disabled', true);
							jQuery(".massactionother").hide();	/* To disable any div open */
						}
					});

					$('.massactionform').submit(function (event) {

						var action = "<?php echo htmlspecialchars($_SERVER['PHP_SELF']. '?id=' .$id, ENT_QUOTES, 'UTF-8'); ?>";

						var actionValue = $('input[name="action"]').val();

						var TSelectedLines = $('#selectedLines').val().split(',');

						var token = "<?php echo newToken() ?>";

						if (actionValue == 'predelete_lines') {
							event.preventDefault();

						}
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

		$langs->load('split@split');

		$contexts = explode(':',$parameters['context']);
//		var_dump($contexts);exit;

		// TODO make it work on invoices and orders before adding this button
		if(/*in_array('ordercard',$contexts) ||*/ in_array('propalcard',$contexts) /*|| in_array('invoicecard',$contexts)*/) {

			$rightCreate = method_exists($user,'hasRight') ? $user->hasRight($object->element,'create') : $user->rights->{$object->element}->creer;
			$rightWrite = method_exists($user,'hasRight') ? $user->hasRight($object->element,'write') : $user->rights->{$object->element}->write;

			$displayButton = ($object->statut == 0 && ($rightCreate || $rightWrite));

			if(GETPOST('actionSplitDelete') == 'ok') {
				setEventMessage($langs->trans('SplitDeleteOk'));
			}
			else if(GETPOST('actionSplit') == 'ok') {
				$url = GETPOST('new_url');
				if (!empty($url)) $url = '- '.$url;
				setEventMessage($langs->trans('SplitOk', $url));
			}
			else if(GETPOST('actionSplitCopy') == 'ok') {
				$url = GETPOST('new_url');
				if (!empty($url)) $url = '- '.$url;
				setEventMessage($langs->trans('SplitCopyOk', $url));
			}
			if ($displayButton) {

				if($object->element=='facture')$idvar = 'facid';
				else $idvar='id';
				if($object->element == 'propal') {
					if((float)DOL_VERSION >= 4.0) $fiche = '/comm/propal/card.php';
					else $fiche = '/comm/propal.php';
				}
				else if($object->element == 'commande') {
					if(floatval(DOL_VERSION) >= 3.7) $fiche = '/commande/card.php';
					else $fiche = '/commande/fiche.php';
				}
				else if($object->element == 'facture') {
					if(floatval(DOL_VERSION) >= 6.0) $fiche = '/compta/facture/card.php';
					else $fiche = '/compta/facture.php';
				}

				$token = function_exists('newToken')?newToken():$_SESSION['newtoken'];
				?><script type="text/javascript">
					$(document).ready(function() {

						var split_bt = $('<div class="inline-block divButAction"><a id="split_it" href="javascript:;" class="butAction"><?php echo  $langs->trans('SplitIt' )?></a></div>');

						$('div.fiche div.tabsAction').append(split_bt);

						split_bt.click(function() {
							$('#pop-split').remove();
							$('body').append('<div id="pop-split"></div>');

							$.get('<?php echo dol_buildpath('/split/script/showLines.php',1).'?id='.$object->id.'&element='.$object->element ?>', function(data) {
								$('#pop-split').html(data)

								$('#pop-split').dialog({
									title:'<?php echo $langs->transnoentities('SplitThisDocument') ?>'
									,width:'80%'
									,modal: true
									,buttons: [
										{
											text: "<?php echo $langs->transnoentities('SimplyDelete', $object->ref); ?>",
											click: function() {

												$('#splitform input[name=action]').val('delete');

												$.post('<?php echo dol_buildpath('/split/script/splitLines.php',1) ?>', $('#splitform').serialize(), function() {
													document.location.href="<?php echo dol_buildpath($fiche,1).'?id='.$object->id.'&actionSplitDelete=ok&token='.$token; ?>";
												});

												$( this ).dialog( "close" );
											}
										},
										{
											text: "<?php echo $langs->transnoentities('SimplyCopy'); ?>",
											title: "<?php echo $langs->transnoentities('SimplyCopyTitle'); ?>",
											click: function() {

												$('#splitform input[name=action]').val('copy');

												$.ajax({
													url: '<?php echo dol_buildpath('/split/script/splitLines.php', 1); ?>'
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
													url: '<?php echo dol_buildpath('/split/script/splitLines.php', 1); ?>'
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

						});

					});

				</script><?php
			}
		}
		return 0;
	}
}

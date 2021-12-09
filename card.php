<?php

require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/massaction/class/massaction.class.php');
dol_include_once('/massaction/lib/massaction.lib.php');

if(empty($user->rights->massaction->read)) accessforbidden();

$langs->load('massaction@massaction');

$action = GETPOST('action');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref');

$mode = 'view';
if (empty($user->rights->massaction->write)) $mode = 'view'; // Force 'view' mode if can't edit object
else if ($action == 'create' || $action == 'edit') $mode = 'edit';

$object = new massaction($db);

if (!empty($id)) $object->load($id, '');
elseif (!empty($ref)) $object->loadBy($ref, 'ref');

$hookmanager->initHooks(array('massactioncard', 'globalcard'));

/*
 * Actions
 */

$parameters = array('id' => $id, 'ref' => $ref, 'mode' => $mode);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

// Si vide alors le comportement n'est pas remplacé
if (empty($reshook))
{
	$error = 0;
	switch ($action) {
		case 'save':
			$object->setValues($_REQUEST); // Set standard attributes

//			$object->date_other = dol_mktime(GETPOST('starthour'), GETPOST('startmin'), 0, GETPOST('startmonth'), GETPOST('startday'), GETPOST('startyear'));

			// Check parameters
//			if (empty($object->date_other))
//			{
//				$error++;
//				setEventMessages($langs->trans('warning_date_must_be_fill'), array(), 'warnings');
//			}

			// ...

			if ($error > 0)
			{
				$mode = 'edit';
				break;
			}

			$object->save(empty($object->ref));

			header('Location: '.dol_buildpath('/massaction/card.php', 1).'?id='.$object->id);
			exit;

		case 'confirm_clone':
			$object->cloneObject();

			header('Location: '.dol_buildpath('/massaction/card.php', 1).'?id='.$object->id);
			exit;

		case 'modif':
			if (!empty($user->rights->massaction->write)) $object->setDraft();

			break;
		case 'confirm_validate':
			if (!empty($user->rights->massaction->write)) $object->setValid();

			header('Location: '.dol_buildpath('/massaction/card.php', 1).'?id='.$object->id);
			exit;

		case 'confirm_delete':
			if (!empty($user->rights->massaction->write)) $object->delete();

			header('Location: '.dol_buildpath('/massaction/list.php', 1));
			exit;

		// link from llx_element_element
		case 'dellink':
			$object->deleteObjectLinked(null, '', null, '', GETPOST('dellinkid'));
			header('Location: '.dol_buildpath('/massaction/card.php', 1).'?id='.$object->id);
			exit;

	}
}


/**
 * View
 */

$title=$langs->trans("massaction");
llxHeader('',$title);

if ($action == 'create' && $mode == 'edit')
{
	load_fiche_titre($langs->trans("Newmassaction"));
	dol_fiche_head();
}
else
{
	$head = massaction_prepare_head($object);
	$picto = 'generic';
	dol_fiche_head($head, 'card', $langs->trans("massaction"), 0, $picto);
}

$formcore = new TFormCore;
$formcore->Set_typeaff($mode);

$form = new Form($db);

$formconfirm = getFormConfirmmassaction($PDOdb, $form, $object, $action);
if (!empty($formconfirm)) echo $formconfirm;

$TBS=new TTemplateTBS();
$TBS->TBS->protect=false;
$TBS->TBS->noerr=true;

if ($mode == 'edit') echo $formcore->begin_form($_SERVER['PHP_SELF'], 'form_massaction');

$linkback = '<a href="'.dol_buildpath('/massaction/list.php', 1).'">' . $langs->trans("BackToList") . '</a>';
print $TBS->render('tpl/card.tpl.php'
	,array() // Block
	,array(
		'object'=>$object
		,'view' => array(
			'mode' => $mode
			,'action' => 'save'
			,'urlcard' => dol_buildpath('/massaction/card.php', 1)
			,'urllist' => dol_buildpath('/massaction/list.php', 1)
			,'showRef' => ($action == 'create') ? $langs->trans('Draft') : $form->showrefnav($object, 'ref', $linkback, 1, 'ref', 'ref', '')
			,'showLabel' => $formcore->texte('', 'label', $object->label, 80, 255)
//			,'showNote' => $formcore->zonetexte('', 'note', $object->note, 80, 8)
			,'showStatus' => $object->getLibStatut(1)
		)
		,'langs' => $langs
		,'user' => $user
		,'conf' => $conf
		,'massaction' => array(
			'STATUS_DRAFT' => massaction::STATUS_DRAFT
			,'STATUS_VALIDATED' => massaction::STATUS_VALIDATED
			,'STATUS_REFUSED' => massaction::STATUS_REFUSED
			,'STATUS_ACCEPTED' => massaction::STATUS_ACCEPTED
		)
	)
);

if ($mode == 'edit') echo $formcore->end_form();

if ($mode == 'view' && $object->id) $somethingshown = $form->showLinkedObjectBlock($object);

llxFooter();
$db->close();

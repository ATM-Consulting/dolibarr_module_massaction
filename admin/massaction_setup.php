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
 * 	\file		admin/massaction.php
 * 	\ingroup	massaction
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include "../../main.inc.php"; // From htdocs directory
if (! $res) {
	$res = @include "../../../main.inc.php"; // From "custom" directory
}
// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/massaction.lib.php';
require_once dirname(__DIR__) .'/class/toolsMassactions.class.php';

// Translations
$langs->load("massaction@massaction");
// Access control
if (! $user->admin) {
	accessforbidden();
}
// Parameters
$action = GETPOST('action', 'aZ09');
/*
 * Actions
 */
if (preg_match('/set_(.*)/', $action, $reg)) {
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code, 'aZ09'), 'chaine', 0, '', $conf->entity) > 0) {
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/', $action, $reg)) {
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0) {
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		dol_print_error($db);
	}
}
/*
 * View
 */
$page_name = "MassActionSetupPage";
llxHeader('', $langs->trans($page_name));
// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
	. $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);
// Configuration header
$head = massactionAdminPrepareHead();
dol_fiche_head(
	$head,
	'settings',
	$langs->trans("ModuleMassActionName"),
	-1,
	"massaction@massaction"
);
// Setup page goes here
$form = new Form($db);
$var = false;
print '<table class="noborder" width="100%">';
toolsMassactions::setupPrintTitle("Parameters");
$predefinedPrice = getDolGlobalInt('SUPPLIER_PROPOSAL_WITH_PREDEFINED_PRICES_ONLY') ? $langs->trans('MassActionSetupCreateProposalSupplierWithConf') : null;

// Example with a yes / no select
toolsMassactions::setupPrintOnOff('MASSACTION_AUTO_DOWNLOAD', $langs->trans('SetupAutoDownloadTitle'), $langs->trans('SetupAutoDownloadDesc'));
toolsMassactions::setupPrintOnOff('MASSACTION_AUTO_SEND_SUPPLIER_PROPOSAL', $langs->trans('MassActionSetupAutoSendSupplierProposal'), $predefinedPrice);
toolsMassactions::setupPrintOnOff('MASSACTION_CREATE_SUPPLIER_PROPOSAL_TO_ZERO', $langs->trans('MassActionSetupCreateProposalSupplierToZero'));
toolsMassactions::setupPrintOnOff('MASSACTION_COPY_REFCLIENT_TO_SUPPLIERPROPOSAL', $langs->trans('MassActionSetupCopyRefClientToSupplierProposal'), $langs->trans('MassActionSetupCreateProposalSupplierToZero'));
print '</table>';
dol_fiche_end(-1);
llxFooter();
$db->close();

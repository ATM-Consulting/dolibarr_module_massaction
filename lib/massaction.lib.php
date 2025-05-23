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
 *	\file		lib/massaction.lib.php
 *	\ingroup	massaction
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function massactionAdminPrepareHead()
{
    global $langs, $conf, $object;

    $langs->load("massaction@massaction");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/massaction/admin/massaction_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/massaction/admin/massaction_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@massaction:/massaction/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@massaction:/massaction/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'massaction');

    return $head;
}

/**
 * Retourne l'URL à donner pour ajouter un destinataire dans un mailing suivant le type de l'objet
 *
 * @param string $elementtype
 * @param object $object
 *
 * @return string
 *
 */
function getUrlToMailingCibles($elementtype, $object){

	$urltoreturn = '';
	if($elementtype == "member") $urltoreturn = $object->getNomURL(0, 0, '', '', '', 0, 1);
	elseif($elementtype == "contact") $urltoreturn = $object->getNomURL(0, '', 0, '', -1, -1);
	else $urltoreturn = $object->getNomURL(0, '', 0, -1);

	return $urltoreturn;
}


function getHtmlSelectElements($entity, $TExcludeId=array(), $element='propal')
{
	global $db,$form,$conf, $langs;

	$TElement = array(0 => '');

	if($element == 'propal') $sql = 'SELECT p.rowid, p.ref,  p.total_ht, s.nom, s.code_client, p.multicurrency_code as currency_code FROM '.$db->prefix().'propal p';
	elseif($element == 'commande') $sql = 'SELECT p.rowid, p.ref,  p.total_ht, s.nom, s.code_client, p.multicurrency_code as currency_code FROM '.$db->prefix().'commande p';
	elseif($element == 'facture') $sql = 'SELECT p.rowid, p.ref,  p.total_ht, s.nom, s.code_client, p.multicurrency_code as currency_code FROM '.$db->prefix().'facture p';
	$sql .= ' INNER JOIN '.$db->prefix().'societe s ON (p.fk_soc = s.rowid)';

	$sql .= ' WHERE p.entity = '.$entity;
	$sql .= ' AND p.fk_statut = 0';

	if(! empty($TExcludeId)) $sql .= ' AND p.rowid NOT IN ('.implode(',', $TExcludeId).')';
	$sql .= ' ORDER BY p.ref';

	dol_syslog('Lib module SPLIT for action "getHtmlSelectPropals" launched by ' . __FILE__ . ' [SQL]= '.$sql, LOG_DEBUG);
	$resql = $db->query($sql);
	if ($resql)
	{
		while ($row = $db->fetch_object($resql))
		{
			if(empty($TElement[$row->rowid])) $TElement[$row->rowid] = '';
			$TElement[$row->rowid] .= $row->ref.' - ';
			if(!empty($row->total_ht)) $TElement[$row->rowid] .= price($row->total_ht, 0, $langs, 1, -1, -1, $row->currency_code);
			$TElement[$row->rowid] .= $row->nom.' ('.$row->code_client.')';
		}
	}
	else
	{
		dol_print_error($db);
		dol_syslog('massaction.lib.php::errorDb ' . $db->lasterror(), LOG_ERR);
	}

	return $form->selectarray('fk_element_split', $TElement, '', 0, 0, 0, '', 0, 0, 0, '', '', 1);
}

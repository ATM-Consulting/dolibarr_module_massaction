<?php
/* Copyright (C) 2025 ATM Consulting
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */


/**
 * Class for MassAction
 */
class toolsMassactions
{
	/**
	 * Renders a configuration table row with an On/Off toggle switch.
	 *
	 * Displays the setting label (with optional tooltip and description) and generates
	 * either an AJAX toggle or a link that forces a page reload to change the setting.
	 *
	 * @param string       $confkey                The global configuration key (DB constant name).
	 * @param string|false $title                  Label text. If false, the $confkey translation is used.
	 * @param string       $desc                   Description text displayed below the label.
	 * @param string|false $help                   Tooltip text or translation key.
	 * @param int          $width                  Width of the toggle column (default: 300).
	 * @param bool         $forcereload            If true, reloads the page on click. If false, uses AJAX.
	 * @param array        $ajaxConstantOnOffInput Additional parameters for the AJAX toggle.
	 * @return void Output is sent directly to the buffer.
	 */
	public static function setupPrintOnOff($confkey, $title = false, $desc = '', $help = false, $width = 300, $forcereload = false, $ajaxConstantOnOffInput = array())
	{
		global $var, $bc, $langs, $conf, $form;
		$var=!$var;

		print '<tr>';
		print '<td>';


		if (empty($help) && !empty($langs->tab_translate[$confkey . '_HELP'])) {
			$help = $confkey . '_HELP';
		}

		if (!empty($help)) {
			print $form->textwithtooltip(($title?$title:$langs->trans($confkey)), $langs->trans($help), 2, 1, img_help(1, ''));
		} else {
			print $title?$title:$langs->trans($confkey);
		}

		if (!empty($desc)) {
			print '<br><small>'.$langs->trans($desc).'</small>';
		}
		print '</td>';
		print '<td align="center" width="20">&nbsp;</td>';
		print '<td align="center" width="'.$width.'">';

		if ($forcereload) {
			$link = $_SERVER['PHP_SELF'].'?action=set_'.$confkey.'&token='. newToken() .'&'.$confkey.'='.intval((empty($conf->global->{$confkey})));
			$toggleClass = empty($conf->global->{$confkey})?'fa-toggle-off':'fa-toggle-on font-status4';
			print '<a href="'.$link.'" ><span class="fas '.$toggleClass.' marginleftonly" style=" color: #999;"></span></a>';
		} else {
			print ajax_constantonoff($confkey, $ajaxConstantOnOffInput);
		}
		print '</td></tr>';
	}
	/**
	 * Generates the HTML header row for a configuration table.
	 *
	 * @param string $title Translation key or label for the first column (default: "Parameter").
	 * @return string Returns the HTML <tr> string.
	 */
	public static function setupPrintTitle($title = 'Parameter') :string
	{
		global $langs;
		$out = '<tr class="liste_titre">';
		$out .= '<td class="titlefield">'.$langs->trans($title) . '</td>';
		$out .= '<td class="titlefield center"  width="20">&nbsp;</td>';
		$out .= '<td class="titlefield center">'.$langs->trans('Value').'</td>';
		$out .= '</tr>';
		return $out;
	}
}

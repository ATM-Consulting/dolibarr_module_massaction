<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   massaction     Module massaction
 *  \brief      Example of a module descriptor.
 *				Such a file must be copied into htdocs/massaction/core/modules directory.
 *  \file       htdocs/massaction/core/modules/modmassaction.class.php
 *  \ingroup    massaction
 *  \brief      Description and activation file for module massaction
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module massaction
 */
class modmassaction extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;

		$this->editor_name = 'ATM Consulting';
		$this->editor_url = 'https://www.atm-consulting.fr';

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 104102; // 104000 to 104999 for ATM CONSULTING
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'massaction';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "ATM Consulting";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = "MassAction";
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "ModuleMassActionDesc";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.7.4';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='massaction@massaction';

		$this->module_parts = array(
			'hooks' => array(
				'globalcard',
				'main',
			)
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/massaction/temp");
		$this->dirs = array();

		// Config pages. Put here list of php page, stored into massaction/admin directory, to use to setup module.
		$this->config_page_url = 'massaction_setup.php@massaction';

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array();		// List of modules id that must be enabled if this module is enabled
		$this->requiredby = array();	// List of modules id to disable if this one is disabled
		$this->conflictwith = array();	// List of modules id this module is in conflict with
		$this->phpmin = array(7,0);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(16,0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("massaction@massaction");

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = array();

        $this->tabs = array();

        // Dictionaries
	    if (! isModEnabled('massaction'))
        {
        	$conf->massaction=new stdClass();
        	$conf->massaction->enabled=0;
        }
		$this->dictionaries=array();

        // Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes

		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r=0;

		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

	}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		$sql = array();

		define('INC_FROM_DOLIBARR',true);

		$this->addEmailTemplateForSupplierProposal();

		return $this->_init($sql, $options);
	}

	/**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function remove($options='')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}

	/**
	 * Add the default email template for supplier proposals.
	 *
	 * @return void
	 */
	private function addEmailTemplateForSupplierProposal() : void
	{
		global $langs;

		$langs->load('massaction@massaction');

		$label = $langs->trans('MassActionSupplierPriceRequest');

		// Check if the template already exists to avoid duplicates
		$sqlCheck = "SELECT COUNT(*) as total FROM " . $this->db->prefix() . "c_email_templates WHERE label = '" . $this->db->escape($label) . "'";
		$res = $this->db->query($sqlCheck);
		$obj = $this->db->fetch_object($res);

		if ($obj->total == 0) {

			$topic = $langs->transnoentities('MassActionSupplierPriceRequest') . ' __REF__';
			$content = $langs->transnoentities('MassActionHello') . " __THIRDPARTY_NAME__,\n\n";
			$content .= $langs->transnoentities('MassActionPleaseFindAttachedOurPriceRequest') . " __REF__.\n\n";
			$content .= $langs->transnoentities('MassActionSincerely') . ",\n\n";
			$content .= "__USER_SIGNATURE__";

			$sql = "INSERT INTO " . $this->db->prefix() . "c_email_templates (entity, type_template, label, topic, content, active) VALUES (";
			$sql .= (int) $conf->entity . ", ";
			$sql .= "'supplier_proposal_send', ";
			$sql .= "'" . $this->db->escape($label) . "', ";
			$sql .= "'" . $this->db->escape($topic) . "', ";
			$sql .= "'" . $this->db->escape($content) . "', ";
			$sql .= "1";
			$sql .= ")";

			$this->db->query($sql);
		}
	}


}

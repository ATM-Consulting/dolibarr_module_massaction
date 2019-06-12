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
 * \file    class/actions_massactionpdf.class.php
 * \ingroup massactionpdf2
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class Actionsmassactionpdf
 */
class Actionsmassactionpdf
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

        $error = 0; // Error counter

        //print_r($parameters); echo "action: " . $action;
        if (strpos($parameters['context'], 'list') !== 0)
        {
            $langs->load('massactionpdf2@massactionpdf2');

            // @TODO Ask for compression format and filename
            if($massaction == 'generate_zip')
            {
                $form = new Form($db);
                $formquestion = array(
                    array('type' => 'other','name' => 'compression_mode','label' => $langs->trans('MassActionPDFGenerateZIPOptionsText'),'value' => $this->selectCompression())
                );
                $text = $langs->trans('MassActionPDFGenerateZIPOptionsText');
                $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?massaction=confirm_generate_zip', $langs->trans('MassActionPDFGenerateZIPOptions'), $text, 'confirm_generate_zip', $formquestion, "yes", 2);
                $this->resprints = $formconfirm;
            }

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
                    $compressmode = GETPOST('compress_mode');
                    $compressmode = 'zip';
                    $filename = GETPOST('filename');
                    $filename = 'dolibarr_'.date('Ymd_his').'.'.$compressmode;

                    // Copy files in a temp directory
                    $tempdir = $diroutputmassaction.'/temp';
                    dol_mkdir($tempdir);
                    foreach ($toarchive as $filepath) {
                        dol_copy($filepath, $tempdir.'/'.basename($filepath), 0, 1);
                    }

                    // @TODO : deal with other compression type than zip
                    // Generate the zip archive
                    dol_compress_dir($tempdir, $diroutputmassaction.'/'.$filename, $compressmode);
                    // Delete temp directory
                    dol_delete_dir_recursive($diroutputmassaction.'/'.$filename);

                    setEventMessage($langs->trans('MassActionPDFZIPGenerated', count($toarchive)));

                    // @TODO : download the file (Automatically ?)
                    if (file_exists($diroutputmassaction.'/'.$filename)) {
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="'.$filename.'"');
                        header('Content-Length: ' . filesize($diroutputmassaction.'/'.$filename));
                        readfile($diroutputmassaction.'/'.$filename);
                    }
                }
            }
        }

        if (! $error) {
            $this->results = array('myreturn' => 999);
            $this->resprints = 'A text to show';
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
        $langs->load('massactionpdf@massactionpdf');

        $error = 0; // Error counter

        //print_r($parameters); print_r($object); echo "action: " . $action;
        if (strpos($parameters['context'], 'list') !== 0)		// do something only for the context 'somecontext1' or 'somecontext2'
        {
            $langs->load('massactionpdf2@massactionpdf2');
            $disabled = false;
            $this->resprints = '<option value="generate_zip"'.($disabled?' disabled="disabled"':'').'>'.$langs->trans("MassActionPDFGenerateZIP").'</option>';
            //$disabled = false;
            //$this->resprints.= '<option value="generate_pdf"'.($disabled?' disabled="disabled"':'').'>'.$langs->trans("MassActionPDFGeneratePDF").'</option>';
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

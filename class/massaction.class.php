<?php

class MassAction {

	public $TErrors = [];

	public $object;

	public $db;

	/**
	 * @param $object
	 */
	public function __construct($db, $object)
	{
		$this->object = $object;
		$this->db = $db;
	}


	public function updateLine($index, $quantity, $marge = '')
	{
		global $langs;

		$rowid = $this->object->lines[$index]->rowid;
		$quantity = $quantity ?? $this->object->lines[$index]->qty;
		$remise_percent = $this->object->lines[$index]->remise_percent;
		$txtva = $this->object->lines[$index]->tva_tx;
		$txlocaltax1 = $this->object->lines[$index]->localtax1_tx;
		$txlocaltax2 = $this->object->lines[$index]->localtax2_tx;
		$desc = $this->object->lines[$index]->desc;
		$subprice = $this->object->lines[$index]->subprice;
		$price_base_type = 'HT';
		$info_bits = $this->object->lines[$index]->info_bits;
		$special_code = $this->object->lines[$index]->special_code;
		$fk_parent_line = $this->object->lines[$index]->fk_parent_line;
		$skip_update_total = $this->object->lines[$index]->skip_update_total;
		$fk_fournprice = $this->object->lines[$index]->fk_fournprice;
		$pa_ht = $this->object->lines[$index]->pa_ht;
		$label = $this->object->lines[$index]->product_label;
		$type = $this->object->lines[$index]->product_type;
		$date_start = $this->object->lines[$index]->date_start;
		$date_end = $this->object->lines[$index]->date_end;
		$array_options = $this->object->lines[$index]->array_options;
		$situation_percent = $this->object->lines[$index]->situation_percent;
		$fk_unit = 0;
		$notrigger = 0;
		$ref_ext = $this->object->lines[$index]->ref_ext;
		$rang = $this->object->lines[$index]->rang;

		if($marge !== null && $marge !== '') $pu_ht = MassAction::getPuByMargin($this->object, $index, $marge, $pa_ht);
		else $pu_ht = $subprice;

		switch ($this->object->element) {
			case "propal":
				$res = $this->object->updateline(
					$rowid, $pu_ht, $quantity,
					$remise_percent, $txtva, $txlocaltax1, $txlocaltax2, $desc, $price_base_type, $info_bits,
					$special_code, $fk_parent_line, $skip_update_total, $fk_fournprice, $pa_ht, $label,
					$type, $date_start, $date_end, $array_options, $fk_unit, 0, $notrigger, $rang
				);
				break;
			case "commande":
				$res = $this->object->updateline(
					$rowid, $desc, $pu_ht, $quantity,
					$remise_percent, $txtva, $txlocaltax1, $txlocaltax2, $price_base_type, $info_bits, $date_start, $date_end,
					$type, $fk_parent_line, $skip_update_total,
					$fk_fournprice, $pa_ht, $label, $special_code, $array_options, $fk_unit, 0, $notrigger, $ref_ext,$rang
				);
				break;
			case "facture":
				$res = $this->object->updateline(
					$rowid, $desc, $pu_ht, $quantity,
					$remise_percent, $date_start, $date_end, $txtva, $txlocaltax1, $txlocaltax2, $price_base_type, $info_bits,
					$type, $fk_parent_line, $skip_update_total,
					$fk_fournprice, $pa_ht, $label, $special_code, $array_options, $situation_percent, $fk_unit, 0, $notrigger, $ref_ext,$rang
				);
			default:
				break;
		}

		if ($res < 0){
			$this->TErrors[] = $langs->trans('ErrorUpdateLine', $index + 1);
		}

	}

	private function getPuByMargin($object, $index, $marge_tx, $pa_ht)
	{
		global $langs;

		$object->lines[$index]->marge_tx = $marge_tx;

		$remise = (100 - $object->lines[$index]->remise_percent) / 100;

		$pu_ht = $pa_ht * (1+($marge_tx/100));

		$pu_ht = $pu_ht / ($remise);

		if(empty(floatval($object->lines[$index]->pa_ht))) {
			$this->TErrors[] = $langs->trans('ErrorPaHT', $index + 1);
		}

		return $pu_ht;
	}

	public function handleErrors($TSelectedLines, $TErrors, $action)
	{
		global $langs;

		$this->TErrors = array_merge($this->TErrors, $TErrors);

		if (empty($this->TErrors)) {
			$this->db->commit();
			if ($action == 'edit_quantity') {
				$confirmMsg = $langs->trans('ConfirmMassEditionQty', count($TSelectedLines));
			} elseif ($action == 'edit_margin') {
				$confirmMsg = $langs->trans('ConfirmMassEditionMargin', count($TSelectedLines));
			}
			setEventMessage($confirmMsg);
			header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $this->object->id);
		} else {
			$this->db->rollback();
			if(!empty($this->db->lasterror())) {
				$this->TErrors[] = $this->db->lasterror();
			}
			setEventMessages('Errors', $this->TErrors, 'errors');
			if ($action == 'edit_quantity') {
				dol_syslog(get_class($this)."::class MassAction - edit_quantity : Transaction not successful" .$this->db->lasterror(), LOG_ERR);
			} elseif ($action == 'edit_margin') {
				dol_syslog(get_class($this)."::class MassAction - edit_margin : Transaction not successful" .$this->db->lasterror(), LOG_ERR);
			}
		}
	}

	public static function getFormConfirm($action, $TSelectedLines, $id, $form)
	{
		global $langs;

		$nbrOfSelectedLines = count($TSelectedLines);

		$page = $_SERVER["PHP_SELF"] . '?id=' . $id;

		$formConfirm = '';

		if ($action == 'predelete') {
			$formConfirm = $form->formconfirm(
				$page,
				$langs->trans("ConfirmMassDeletion"),
				$langs->trans("ConfirmMassDeletionQuestion", $nbrOfSelectedLines),
				"delete_lines",
				null,
				'',
				0,
				200, 500,
				1
			);
		}
		if ($action == "preeditquantity") {
			$formConfirm = $form->formconfirm(
				$page,
				$langs->trans('MassActionConfirmEdit'),
				$langs->trans('MassActionConfirmEditQuantity', $nbrOfSelectedLines),
				'edit_quantity',
				array(
					array(
						'label' => 'Quantité',
						'type' => 'text',
						'name' => 'quantity'
					)
				),
				'',
				0,
				200, 500,
				1
			);
		}
		if ($action == 'preeditmargin') {
			$formConfirm = $form->formconfirm(
				$page,
				$langs->trans('MassActionConfirmEdit'),
				$langs->trans('MassActionConfirmEditMargin', $nbrOfSelectedLines),
				'edit_margin',
				array(
					array(
						'label' => 'Marge',
						'type' => 'text',
						'name' => 'marge_tx'
					)
				),
				'',
				0,
				200, 500,
				1
			);
		}

		return $formConfirm;
	}

	public static function getMassActionButton($permissionToAdd, $form)
	{
		global $langs;

		$arrayOfMassActions = array();

		if(!$permissionToAdd) {
			return 0;
		}

		$arrayOfMassActions['cut'] = img_picto('', 'fa-scissors', 'class="pictofixedwidth"') . $langs->trans("Cut");
		$arrayOfMassActions['preeditmargin'] = img_picto('', 'fa-pen', 'class="pictofixedwidth"') . $langs->trans("EditMargin");
		$arrayOfMassActions['preeditquantity'] = img_picto('', 'fa-pen', 'class="pictofixedwidth"') . $langs->trans("EditQty");
		$arrayOfMassActions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"') . $langs->trans("Delete");

		$massActionButton = $form->selectMassAction('', $arrayOfMassActions);

		return $massActionButton;
	}

}

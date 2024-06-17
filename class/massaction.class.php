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


	/**
	 * @param int $index
	 * @param float $quantity
	 * @param float|null $marge
	 * @return int
	 */
	public function updateLine(int $index, ?float $quantity, ?float $marge = null): int
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
		$situation_percent = $this->object->lines[$index]->situation_percent ?? null;
		$fk_unit = 0;
		$notrigger = 0;
		$ref_ext = $this->object->lines[$index]->ref_ext;
		$rang = $this->object->lines[$index]->rang;

		if($marge !== null && $marge !== '') {
			$marge = floatval($marge);
			$pu_ht = MassAction::getPuByMargin($this->object, $index, $marge, $pa_ht);
		} else $pu_ht = $subprice;

		if (!empty($this->TErrors)) {
			return -1;
		}

		switch ($this->object->element) {
			case "propal":
				$resUpdate = $this->object->updateline(
					$rowid, $pu_ht, $quantity,
					$remise_percent, $txtva, $txlocaltax1, $txlocaltax2, $desc, $price_base_type, $info_bits,
					$special_code, $fk_parent_line, $skip_update_total, $fk_fournprice, $pa_ht, $label,
					$type, $date_start, $date_end, $array_options, $fk_unit, 0, $notrigger, $rang
				);
				break;
			case "commande":
				$resUpdate = $this->object->updateline(
					$rowid, $desc, $pu_ht, $quantity,
					$remise_percent, $txtva, $txlocaltax1, $txlocaltax2, $price_base_type, $info_bits, $date_start, $date_end,
					$type, $fk_parent_line, $skip_update_total,
					$fk_fournprice, $pa_ht, $label, $special_code, $array_options, $fk_unit, 0, $notrigger, $ref_ext,$rang
				);
				break;
			case "facture":
				$resUpdate = $this->object->updateline(
					$rowid, $desc, $pu_ht, $quantity,
					$remise_percent, $date_start, $date_end, $txtva, $txlocaltax1, $txlocaltax2, $price_base_type, $info_bits,
					$type, $fk_parent_line, $skip_update_total,
					$fk_fournprice, $pa_ht, $label, $special_code, $array_options, $situation_percent, $fk_unit, 0, $notrigger, $ref_ext,$rang
				);
				break;
			default:
				break;
		}

		if ($resUpdate < 0){
			$this->TErrors[] = $langs->trans('ErrorUpdateLine', $index + 1);
		}

		return $resUpdate;

	}

	/**
	 * @param int $index
	 * @param int $selectedLine
	 * @return int
	 */
	public function deleteLine(int $index, int $selectedLine): int
	{
		global $user, $langs;

		switch ($this->object->element) {
			case "commande":
				$resDelete = $this->object->deleteline($user, $selectedLine);
				break;
			case "facture":
			case "propal":
				$resDelete = $this->object->deleteline($selectedLine);
				break;
			default:
				break;
		}

		if ($resDelete < 0) {
			$this->TErrors = $langs->trans('ErrorDeleteLine', $index + 1);
		}

		return $resDelete;
	}

	/**
	 * @param CommonObject $object
	 * @param int $index
	 * @param float $marge_tx
	 * @param float $pa_ht
	 * @return float
	 */
	private function getPuByMargin(CommonObject $object, int $index, float $marge_tx, float $pa_ht): float
	{
		global $langs;

		$object->lines[$index]->marge_tx = $marge_tx;

		$remise = (100 - $object->lines[$index]->remise_percent) / 100;

		$pu_ht = $pa_ht * (1+($marge_tx/100));

		$pu_ht = $pu_ht / ($remise);

		if (empty(floatval($object->lines[$index]->pa_ht))) {
			$this->TErrors[] = $langs->trans('ErrorPaHT', $index + 1);
			return -1;
		}

		return $pu_ht;
	}

	public static function checkFields($action, $field)
	{

		global $langs;

		$TErrors = array();

		$errorMessage = $action == 'edit_quantity' ? 'ErrorQtyAlpha' : 'ErrorMarginAlpha';

		if (!is_numeric($field) && $field !== '0') {
			$TErrors[] = $langs->trans($errorMessage);
		} else {
			// Convertir en float seulement si la validation est réussie
			$field = floatval($field);

			if($action == 'edit_quantity' && $field < 0) {
				$TErrors[] = $langs->trans('ErrorQtyNegative');
			}
		}

		return $TErrors;

	}

	/**
	 * @param array $TSelectedLines
	 * @param array $TErrors
	 * @param string $action
	 * @return void
	 * @throws Exception
	 */
	public function handleErrors(array $TSelectedLines, array $TErrors, string $action): void
	{
		global $langs;

		$this->TErrors = array_merge($this->TErrors, $TErrors);

		if (empty($this->TErrors)) {
			if ($action == 'edit_quantity') {
				$confirmMsg = $langs->trans('ConfirmMassEditionQty', count($TSelectedLines));
			} elseif ($action == 'edit_margin') {
				$confirmMsg = $langs->trans('ConfirmMassEditionMargin', count($TSelectedLines));
			} elseif ($action == 'delete_lines') {
				$confirmMsg = $langs->trans('ConfirmMassDeletionLines', count($TSelectedLines));
			}
			setEventMessage($confirmMsg);
			header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $this->object->id);
		} else {
			if(!empty($this->db->lasterror())) {
				$this->TErrors[] = $this->db->lasterror();
			}
			setEventMessages('Errors', $this->TErrors, 'errors');
			$TErrorsForMsg = implode("\r\n", $this->TErrors);
			dol_syslog(get_class($this)."::class MassAction - ".$action." : Transaction not successful" .$this->db->lasterror() . " TErrors : " . $TErrorsForMsg, LOG_ERR);
		}
	}

	/**
	 * @param string $action
	 * @param array $TSelectedLines
	 * @param int $id
	 * @param Form $form
	 * @return string
	 */
	public static function getFormConfirm(string $action, array $TSelectedLines, int $id, Form $form): string
	{
		global $langs;
		$question = null;
		$title = null;
		$actionInFormConfirm = null;
		$formQuestion = null;

		$nbrOfSelectedLines = count($TSelectedLines);

		$page = $_SERVER["PHP_SELF"] . '?id=' . $id;

		if ($action == 'predelete') {

			$actionInFormConfirm = 'delete_lines';
			$title = $langs->trans("ConfirmMassDeletion");
			$question = $langs->trans("ConfirmMassDeletionQuestion", $nbrOfSelectedLines);
			$formQuestion = null;

		} elseif ($action == "preeditquantity") {

			$actionInFormConfirm = 'edit_quantity';
			$title = $langs->trans('MassActionConfirmEdit');

			$formQuestion = array(
				array(
					'label' => 'Quantité',
					'type' => 'text',
					'name' => 'quantity'
				)
			);

		} elseif ($action == 'preeditmargin') {

			$actionInFormConfirm = 'edit_margin';
			$title = $langs->trans('MassActionConfirmEdit');
			$question = $langs->trans('MassActionConfirmEditMargin', $nbrOfSelectedLines);
			$formQuestion = array(
				array(
					'label' => 'Marge',
					'type' => 'text',
					'name' => 'marge_tx'
				)
			);

		}

		if(empty($actionInFormConfirm) || empty($title) ) {
			return '';
		}

		$formConfirm = $form->formconfirm(
			$page, $title, $question, $actionInFormConfirm, $formQuestion,
			'1', // Vu avec CDP Benoit . préselectionné sur oui
			0,
			200, 500,
			0
		);

		return $formConfirm;
	}

	/**
	 * @param Form $form
	 * @return string
	 */
	public static function getMassActionButton(Form $form): string
	{
		global $langs;
		$nameIcon = ((float) DOL_VERSION <= 18.0) ? 'fa-scissors' : 'fa-cut';
		$arrayOfMassActions = array();

		$arrayOfMassActions['cut'] = img_picto('', $nameIcon, 'class="pictofixedwidth"') . $langs->trans("MassActionCut");
		if (isModEnabled('margin') && getDolGlobalInt('DISPLAY_MARGIN_RATES')) {
			$arrayOfMassActions['preeditmargin'] = img_picto('', 'fa-pen', 'class="pictofixedwidth"') . $langs->trans("EditMargin");
		}
		$arrayOfMassActions['preeditquantity'] = img_picto('', 'fa-pen', 'class="pictofixedwidth"') . $langs->trans("EditQty");
		$arrayOfMassActions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"') . $langs->trans("Delete");

		$massActionButton = $form->selectMassAction('', $arrayOfMassActions);

		return $massActionButton;
	}

}

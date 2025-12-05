<?php

include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';

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
		} elseif ($action == 'preSelectSupplierPrice') {
			$actionInFormConfirm = 'createSupplierPrice';
			$title = $langs->trans('MassActionConfirmEdit');
			$question = $langs->trans('MassActionConfirmcreateSupplierPrice', $nbrOfSelectedLines);
			$formQuestion = array(
				array(
					'label' => $langs->trans('MassActionSelectSupplier'),
					'type' => 'other',
					'name' => 'supplierPrice',
					'value' => $form->select_company('', 'supplierid', '(s.fournisseur:IN:' . SOCIETE::SUPPLIER .')' , 1, 1, 0, [], 0, 'minwidth100', '', '', 1, [], true),
				),
			);

			if (getDolGlobalInt('MASSACTION_AUTO_SEND_SUPPLIER_PROPOSAL')) {
				$formQuestion[] = array(
					'label' => $langs->trans('MassActionSelectModelEmail'),
					'type' => 'other',
					'name' => 'modelEmail',
					'value' => $form->selectModelMail("", 'supplier_proposal_send', 0, 0, ''),
				);
			}

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
		global $langs, $user;

		$nameIcon = ((float) DOL_VERSION <= 18.0) ? 'fa-scissors' : 'fa-cut';
		$arrayOfMassActions = array();

		$arrayOfMassActions['cut'] = img_picto('', $nameIcon, 'class="pictofixedwidth"') . $langs->trans("MassActionCut");
		if (isModEnabled('margin') && getDolGlobalInt('DISPLAY_MARGIN_RATES')) {
			$arrayOfMassActions['preeditmargin'] = img_picto('', 'fa-pen', 'class="pictofixedwidth"') . $langs->trans("EditMargin");
		}
		$arrayOfMassActions['preeditquantity'] = img_picto('', 'fa-pen', 'class="pictofixedwidth"') . $langs->trans("EditQty");
		$arrayOfMassActions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"') . $langs->trans("Delete");

		if ($user->hasRight('supplier_proposal', 'creer') || $user->hasRight('supplier_proposal', 'lire')) {
			$arrayOfMassActions['preSelectSupplierPrice'] = img_picto('', 'fa-file-signature ', 'class="pictofixedwidth"') . $langs->trans("MassActionCreateSupplierPrice");
		}

		$massActionButton = $form->selectMassAction('', $arrayOfMassActions);

		return $massActionButton;
	}

	/**
	 * Main function to orchestrate the creation of supplier price requests.
	 * This is the entry point for the logic.
	 *
	 * @param CommonObject $object The original source object (e.g., Order, Proposal).
	 * @param array $TSelectedLines The lines to process.
	 * @param array $supplierIds The supplier to send the supplier proposal
	 * @param int $templateId The email template to use for sending the supplier proposal
	 * * @return void                This function does not return a value but outputs messages.
	 */
	public static function handleCreateSupplierPriceAction(CommonObject $object, array $TSelectedLines, array $supplierIds, int $templateId): void
	{
		global $db, $user, $langs, $conf;

		// 1. Initial checks for permissions and input data
		if (!self::hasRequiredPermissionsToCreateSupplierProposal($user)) {
			setEventMessage($langs->trans("ErrorForbidden"), 'errors');
			dol_syslog("MassAction - Error: User does not have required permissions to create supplier price requests.", LOG_ERR);
			return;
		}

		if (empty($supplierIds)) {
			setEventMessage($langs->trans("MassActionErrorNoSuppliersSelected"), 'errors');
			dol_syslog("MassAction - Error: No suppliers were selected.", LOG_ERR);
			return;
		}

		// 2. Retrieve the details of the selected product lines
		$selectedLinesDetails = self::getSelectedLineDetails($object, $TSelectedLines);
		if (empty($selectedLinesDetails)) {
			setEventMessage($langs->trans('MASSACTIONERRORCOULDNOTFINDLINEDETAILS'), 'errors');
			dol_syslog("MassAction - Error: Could not find line details.", LOG_ERR);
			return;
		}

		$successMessages = [];
		$errorMessages = [];

		// 3. Loop through each supplier to process their price request
		foreach ($supplierIds as $supplierId) {
			try {
				$resultMessages = self::processSingleSupplier($supplierId, $selectedLinesDetails, $templateId, $object);
				$successMessages = array_merge($successMessages, $resultMessages);
			} catch (Exception $e) {
				$errorMessages[] = $e->getMessage();
			}
		}

		// 4. Display status messages at the end of the process
		if (!empty($successMessages)) {
			setEventMessage(implode('<br>', $successMessages), 'mesgs');
		}
		if (!empty($errorMessages)) {
			setEventMessage(implode('<br>', $errorMessages), 'errors');
			dol_syslog("MassAction - Error: " . implode(', ', $errorMessages), LOG_ERR);;
		}
	}

	/**
	 * Processes the creation of a price request for a single supplier.
	 *
	 * @param int $supplierId                    The ID of the supplier.
	 * @param array $selectedLinesDetails         An array of selected line details.
	 * @param int $templateId                    The ID of the email template to use for sending the supplier proposal.
	 * @param CommonObject $object              The original source object (e.g., Order, Proposal).
	 * @return array                            An array of success messages.
	 * @throws Exception                        If an error occurs during the process.
	 */
	public static function processSingleSupplier(int $supplierId, array $selectedLinesDetails, int $templateId, CommonObject $object): array
	{
		global $db, $langs;

		$supplier = new Societe($db);
		$supplier->fetch($supplierId);
		$successLog = [];

		$db->begin();

		try {
			$supplierProposal = self::createSupplierProposal($supplierId, $selectedLinesDetails, $object);
			$successLog[] = $langs->trans("MassActionSupplierPriceRequestCreatedFor", $supplier->name, $supplierProposal->getNomUrl(1, '', '', 1));

			self::generateProposalPdf($supplierProposal);

			if (getDolGlobalInt('MASSACTION_AUTO_SEND_SUPPLIER_PROPOSAL') && !empty($templateId)) {
				self::sendProposalByEmail($supplierProposal, $supplier, $templateId);
				$successLog[] = $langs->trans("MassActionEmailSentTo", $supplier->email);
			}

			$db->commit();
			return $successLog;

		} catch (Exception $e) {
			$db->rollback();
			throw new Exception($langs->trans("MassActionErrorProcessingSupplier", $supplier->name) . ': ' . $e->getMessage());
		}
	}

	/**
	 * Creates and populates a supplier price request.
	 *
	 * * @param int $supplierId Id of the supplier
	 * * @param array $lines Array of lines to process
	 * * @param CommonObject $object The original source object (e.g., Order, Proposal)
	 * * @return SupplierProposal The created supplier proposal request.
	 * * @throws Exception if creation fails.
 */
	public static function createSupplierProposal(int $supplierId, array $lines, CommonObject $object): SupplierProposal
	{
		global $db, $user, $langs;

		$supplierProposal = new SupplierProposal($db);
		$supplierProposal->socid = $supplierId;
		$supplierProposal->date_creation = dol_now();
		if (!empty($object->fk_project)) {
			$supplierProposal->fk_project = $object->fk_project;
		}
		if ($supplierProposal->create($user) < 0) {
			throw new Exception($langs->trans("MassActionFailedToCreateSupplierProposal" . $supplierProposal->error));
		}

		foreach ($lines as $line) {
			if (getDolGlobalInt("MASSACTION_CREATE_SUPPLIER_PROPOSAL_TO_ZERO")) {
				$line->subprice = 0;
				$line->fk_fournprice = 0;
				$line->pa_ht = 0;
			}
			$supplierProposal->addline($line->desc, $line->subprice, $line->qty, $line->tva_tx, 0, 0, $line->fk_product, $line->remise_percent, 'HT', $line->price, 0, $line->product_type, $line->rang, $line->special_code, $line->fk_parent_line, $line->fk_fournprice, $line->pa_ht, $line->label);
		}

		$supplierProposal->valid($user);
		$supplierProposal->add_object_linked($object->element, $object->id);
		$supplierProposal->fetch($supplierProposal->id);

		return $supplierProposal;
	}

	/**
	 * Generates the PDF document for a price request.
	 *
	 * @param SupplierProposal $proposal The price request to generate the PDF for.
	 * @throws Exception if PDF generation fails.
	 */
	public static function generateProposalPdf(SupplierProposal $proposal): void
	{
		global $langs;

		if ($proposal->generateDocument($proposal->modelpdf, $langs) <= 0) {
			throw new Exception($langs->trans("MassActionFailedToGeneratePDF") . $proposal->error);
		}
	}

	/**
	 * Sends the price request by email to the supplier.
	 *
	 * @param SupplierProposal $supplierProposal The supplier price request to send.
	 * @param Societe $supplier The supplier to send the email to.
	 * @param int $templateId The email template to use for sending the email.
	 * @throws Exception if the email sending fails.
	 */
	public static function sendProposalByEmail(SupplierProposal $supplierProposal, Societe $supplier, int $templateId): void
	{
		global $db, $user, $langs, $conf;

		$objectref_sanitized = dol_sanitizeFileName($supplierProposal->ref);
		$dir = $conf->supplier_proposal->dir_output . "/" . $objectref_sanitized;
		$attachment_filename = $dir . "/" . $objectref_sanitized . ".pdf";

		$formmail = new FormMail($db);

		$template = $formmail->getEMailTemplate($db, 'supplier_proposal_send', $user, $langs, $templateId);
		$substitutionarray = getCommonSubstitutionArray($langs, 0, null, $supplierProposal);
		complete_substitutions_array($substitutionarray, $langs, $supplierProposal);
		$subject = make_substitutions($template->topic, $substitutionarray, $langs);
		$content = nl2br(make_substitutions($template->content, $substitutionarray, $langs));

		$mail = new CMailFile(
			$subject, $supplier->email, $user->email, $content,
			[$attachment_filename], ['application/pdf'], [$objectref_sanitized . ".pdf"],
			'', '', -1, 1, getDolGlobalString('MAIN_MAIL_ERRORS_TO')
		);

		if (!$mail->sendfile()) {
			$errorDetails = is_array($supplier->error) ? implode(', ', $supplier->error) : $supplier->error;
			throw new Exception($langs->trans("MassActionFailedToSendEmail").  $errorDetails);
		}

	}

	/**
	 * Checks if the user has the required permissions.
	 *
	 * @param User $user The user object.
	 * @return bool
	 */
	public static function hasRequiredPermissionsToCreateSupplierProposal(User $user): bool
	{
		return $user->hasRight('supplier_proposal', 'creer') || $user->hasRight('supplier_proposal', 'lire');
	}

	/**
	 * Retrieves the details of the selected product lines.
	 *
	 * @param CommonObject $object The source object (e.g., Proposal).
	 * @param array $selectedLineIds An array of selected line IDs.
	 * @return array
	 */
	public static function getSelectedLineDetails(CommonObject $object, array $selectedLineIds): array
	{
		$details = [];
		if (empty($selectedLineIds) || !is_array($object->lines)) {
			return $details;
		}

		foreach ($object->lines as $line) {
			if (in_array($line->id, $selectedLineIds)) {
				$details[] = $line;
			}
		}
		return $details;
	}

}

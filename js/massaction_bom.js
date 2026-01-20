/* MassAction BOM helpers (shared by card and popin). */
(function ($, window) {
	'use strict';

	if (typeof window.massactionConfig === 'undefined') {
		return;
	}

	var config = window.massactionConfig;
	var checkboxSelectors = {
		global: '#massaction-checkall',
		products: '#massaction-checkall-products',
		services: '#massaction-checkall-services'
	};

	function appendHeaderCell(tableSelector, headerHtml) {
		var header = $(tableSelector + ' .liste_titre');
		if (!header.length || header.find('.massaction-header').length) {
			return;
		}
		header.append('<td class="center massaction-header liste_titre">' + (headerHtml || '') + '</td>');
	}

	function addCheckboxesToTable(tableSelector, headerHtml) {
		var count = 0;

		$(tableSelector + ' tbody tr').each(function () {
			var rowId = $(this).attr('id');
			if (rowId && rowId.startsWith('row-')) {
				count++;
				var dataId = $(this).data('id');
				if (!$(this).find('.checkforselect').length) {
					$(this).append('<td class="nowrap" align="center"><input id="cb' + dataId + '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' + dataId + '"></td>');
				}
			} else if (!$(this).find('td:first').is('[colspan="100%"]')) {
				$(this).append('<td></td>');
			}
		});

		if (count > 0) {
			appendHeaderCell(tableSelector, headerHtml);
		}

		return count;
	}

	function showCheckboxes() {
		if (!config.enableCheckboxes) {
			return;
		}

		if (config.isBomContext) {
			var productHeader = ''
				+ '<div class="checkallactions massaction-checkall-line" style="display:block;">'
				+ '<input type="checkbox" id="massaction-checkall" name="checkforselects" class="checkallactions" title="' + config.labels.selectAll + '">'
				+ '</div>'
				+ '<div class="checkallactions massaction-checkall-line" style="display:block;">'
				+ '<input type="checkbox" id="massaction-checkall-products" name="checkforselects_products" class="checkallactions" title="' + config.labels.products + '">'
				+ '</div>';
			var serviceHeader = ''
				+ '<div class="checkallactions massaction-checkall-line" style="display:block;">'
				+ '<input type="checkbox" id="massaction-checkall-services" name="checkforselects_services" class="checkallactions" title="' + config.labels.services + '">'
				+ '</div>';
			addCheckboxesToTable('#tablelines', productHeader);
			addCheckboxesToTable('#tablelinesservice', serviceHeader);
		} else {
			var defaultHeader = ''
				+ '<div class="checkallactions massaction-checkall-line" style="display:block;">'
				+ '<input type="checkbox" id="massaction-checkall" name="checkforselects" class="checkallactions">'
				+ '</div>';
			addCheckboxesToTable('#tablelines', defaultHeader);
		}
	}

	function syncCheckAllState() {
		if (!config.isBomContext) {
			return;
		}

		var productBoxes = $('#tablelines .checkforselect');
		var serviceBoxes = $('#tablelinesservice .checkforselect');
		var hasProducts = productBoxes.length > 0;
		var hasServices = serviceBoxes.length > 0;

		var allProductChecked = hasProducts && productBoxes.filter(':checked').length === productBoxes.length;
		var allServiceChecked = hasServices && serviceBoxes.filter(':checked').length === serviceBoxes.length;

		$(checkboxSelectors.products).prop('checked', allProductChecked).prop('disabled', !hasProducts);
		$(checkboxSelectors.services).prop('checked', allServiceChecked).prop('disabled', !hasServices);
		$(checkboxSelectors.global).prop('checked', hasProducts && hasServices && allProductChecked && allServiceChecked)
			.prop('disabled', !hasProducts && !hasServices);
	}

	function updateSelectedLines() {
		var selected = [];
		$('.checkforselect:checked').each(function () {
			selected.push($(this).val());
		});
		$('#selectedLines').val(selected.join(','));
	}

	function buildMassActionForm() {
		var toShow = config.formConfirm !== '' ? config.formConfirm : (config.massActionButton || '');
		var form = ''
			+ '<form method="post" id="massactionForm" action="' + config.actionUrl + '">'
			+ '<input type="hidden" name="token" value="' + config.token + '">'
			+ '<input type="hidden" name="selectedLines" id="selectedLines" value="' + config.selectedLines + '">'
			+ '<input type="hidden" name="action" value="">'
			+ toShow
			+ '</form>';

		var formTarget = $('#addproduct:last-child');
		if (!formTarget.length) {
			formTarget = $('#listbomproducts');
		}
		if (!formTarget.length) {
			formTarget = $('#listbomservices');
		}
		formTarget.before(form);
	}

	function bindSupplierPriceHandlers() {
		if (config.currentAction !== 'preSelectSupplierPrice') {
			return;
		}
		$('#massactionForm').attr('enctype', 'multipart/form-data');
		$('.massaction-remove-file').on('click', function (e) {
			e.preventDefault();
			var fname = $(this).data('filename');
			$('.removedfilehidden').val(fname);
			$('input[name="action"]').val('preSelectSupplierPrice');
			$('#confirm').val('no');
			$('#massactionForm').submit();
		});
		$('.massaction-file-input').on('change', function () {
			if ($(this).val()) {
				$('input[name="action"]').val('preSelectSupplierPrice');
				$('#confirm').val('no');
				$('input[name="sendit"]').val('1');
				$('#massactionForm').submit();
			}
		});
		$('.confirmvalidatebutton').on('click', function () {
			$('input[name="action"]').val('createSupplierPrice');
		});
	}

	function preselectLines() {
		if (config.currentAction === 'predelete'
			|| config.currentAction === 'preeditquantity'
			|| config.currentAction === 'preeditmargin'
			|| config.currentAction === 'preSelectSupplierPrice') {
			var selectedValues = config.selectedLines.split(',');
			$('.checkforselect').each(function () {
				var checkboxValue = $(this).val();
				if (selectedValues.includes(checkboxValue)) {
					$(this).prop('checked', true);
				}
			});
			updateSelectedLines();
			syncCheckAllState();
		}
	}

	function bindCheckboxHandlers() {
		$(document).on('click', checkboxSelectors.global, function () {
			var checked = $(this).is(':checked');
			$('.checkforselect').prop('checked', checked).trigger('change');
			$(checkboxSelectors.products).prop('checked', checked);
			$(checkboxSelectors.services).prop('checked', checked);
			if (typeof initCheckForSelect === 'function') {
				initCheckForSelect(0, 'massaction', 'checkforselect');
			}
		});

		$(document).on('click', checkboxSelectors.products, function () {
			var checked = $(this).is(':checked');
			$('#tablelines .checkforselect').prop('checked', checked).trigger('change');
			if (!checked) {
				$(checkboxSelectors.global).prop('checked', false);
			}
			if (typeof initCheckForSelect === 'function') {
				initCheckForSelect(0, 'massaction', 'checkforselect');
			}
		});

		$(document).on('click', checkboxSelectors.services, function () {
			var checked = $(this).is(':checked');
			$('#tablelinesservice .checkforselect').prop('checked', checked).trigger('change');
			if (!checked) {
				$(checkboxSelectors.global).prop('checked', false);
			}
			if (typeof initCheckForSelect === 'function') {
				initCheckForSelect(0, 'massaction', 'checkforselect');
			}
		});

		$(document).on('change', '.checkforselect', function () {
			$(this).closest('tr').toggleClass('highlight', this.checked);
			updateSelectedLines();
			syncCheckAllState();
		});
	}

	function bindMassActionSelect() {
		$(document).on('change', '.massactionselect', function () {
			var massaction = $(this).val();
			if (massaction && $('.checkforselect:checked').length === 0) {
				if (typeof $.jnotify === 'function') {
					$.jnotify(config.emptySelectionMessage, 'warning', {timeout: 3, type: 'warning', css: 'warning'});
				}
				$(this).val('');
				return;
			}
			$('input[name="action"]').val(massaction);
		});
	}

	$(function () {
		$('input[type="checkbox"].checkforselect').prop('checked', false);
		buildMassActionForm();
		showCheckboxes();
		syncCheckAllState();
		bindSupplierPriceHandlers();
		preselectLines();
		bindCheckboxHandlers();
		bindMassActionSelect();
	});
})(jQuery, window);

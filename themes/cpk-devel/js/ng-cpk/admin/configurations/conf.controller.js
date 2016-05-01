/**
 * Definition for admin module's Approval Controller handling dynamics within an
 * Approval action
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('admin').controller('ApprovalController', ApprovalController).directive('ng-submit-approval', submitApproval);

    ApprovalController.$inject = [];

    var submitApprovalBtn = undefined;

    function ApprovalController() {

	var editedAt = undefined;

	var currentTableRow = {
	    div : undefined,
	    input : undefined
	};

	var vm = this;

	vm.edit = edit;

	vm.inputKeyDown = inputKeyDown;

	vm.inputBlurred = inputBlurred;

	return vm;

	function edit($event) {

	    currentTableRow.div = $event.currentTarget.children[0];
	    currentTableRow.input = currentTableRow.div.nextElementSibling;

	    showCurrentInput();

	    editedAt = (new Date()).getTime();
	}

	function inputKeyDown($event) {
	    if ($event.keyCode === 13) { // Enter

		// Do not submit the form
		$event.preventDefault();

		var newValue = $event.target.value;

		// Commiting changes
		if (setNewDivValue(newValue)) {
		    hideCurrentInput();
		} else {

		    // Perform dummy submit to show what's wrong
		    submitApprovalBtn.click();
		}

	    } else if ($event.keyCode === 27) { // Esc

		// Cancelling changes
		$event.target.value = getNewDivValue();
		hideCurrentInput();
	    }
	}

	function inputBlurred($event) {

	    if ($event.target.type === 'number') {
		// input of type number needs special treatment

		if ((new Date()).getTime() - 100 < editedAt)
		    return;
	    }

	    var newValue = undefined;

	    if ($event.target.type === 'checkbox')
		newValue = $event.target.checked ? '1' : '0';
	    else
		newValue = $event.target.value;

	    if (!setNewDivValue(newValue)) {

		// Cancelling changes
		$event.target.value = getNewDivValue();
	    }
	    hideCurrentInput();
	}

	// private

	/**
	 * Shows input within current table row & hides current div
	 */
	function showCurrentInput() {
	    currentTableRow.input.className = currentTableRow.input.className.replace(/\shidden|hidden\s?/g, '');

	    currentTableRow.div.setAttribute('hidden', 'hidden');

	    currentTableRow.input.focus();
	}

	/**
	 * Hides input within current table row & shows current div
	 */
	function hideCurrentInput() {
	    currentTableRow.input.className = currentTableRow.input.className + ' hidden';

	    currentTableRow.div.removeAttribute('hidden');
	}

	/**
	 * Sets new value to the <ins> element within a div.
	 * 
	 * It also moves all contents into <del> element when no <ins> found &
	 * creates new <ins> element with value provided
	 * 
	 * Returns false only if the field being set is required & it's not met
	 * the conditions
	 * 
	 * @return boolean
	 */
	function setNewDivValue(value) {
	    if (value === '') {

		var isNumber = currentTableRow.input.type === 'number';

		var isRequired = currentTableRow.input.required;

		if (isNumber)
		    value = currentTableRow.input.currentTableRow.input.placeholder;
		else if (isRequired) {
		    return false;
		}
	    }
	    var ins = currentTableRow.div.getElementsByTagName('ins');

	    if (ins.length === 0) {
		var contents = currentTableRow.div.textContent.trim();

		if (contents === value.trim())
		    return true;

		if (contents.length)
		    currentTableRow.div.innerHTML = '<del style="color: red">' + contents + '</del><br>';

		ins = document.createElement('ins');

		ins.style.color = 'green';

		currentTableRow.div.appendChild(ins);
	    } else {

		var del = currentTableRow.div.getElementsByTagName('del');

		if (del.length) {
		    var previousContents = del[0].textContent.trim();

		    if (previousContents === value.trim()) {
			currentTableRow.div.innerHTML = previousContents;
			return true;
		    }

		}

		ins = ins[0];
	    }

	    ins.textContent = value;

	    return true;
	}

	/**
	 * Gets the proposed value of the <ins> element within a div.
	 * 
	 * If <ins> is not found, then are returned the contents of the div.
	 */
	function getNewDivValue() {
	    var ins = currentTableRow.div.getElementsByTagName('ins');

	    var contents;

	    if (ins.length === 0) {
		contents = currentTableRow.div.textContent.trim();
	    } else {
		contents = ins[0].textContent.trim();
	    }

	    return contents;
	}
    }

    function submitApproval() {
	return {
	    restrict : 'A',
	    link : linker
	};

	function linker(scope, elements, attrs) {
	    submitApprovalBtn = elements.context;
	}
    }
})();
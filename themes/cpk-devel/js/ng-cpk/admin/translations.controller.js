/**
 * Definition for admin module's Approval Controller handling dynamics within an
 * Approval action
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('admin').controller('TranslationsController', TranslationsController).directive('ngSubmitBtn', submitBtn).directive('ngNewTranslation',
	    newTranslation).directive('ngNewTranslationTemplate', newTranslationTemplate);

    TranslationsController.$inject = [ 'translateFilter' ];

    const
    LANG_COUNT = 2;

    var submitBtns = {};

    var newTranslationRows = {};

    var translationRows = {};

    function TranslationsController(translate) {

	var currentTranslationRow = {
	    div : undefined,
	    input : undefined,
	    submitBtn : undefined
	};

	var vm = this;

	vm.newTranslations = {};

	vm.editTranslation = editTranslation;

	vm.addTranslation = addTranslation;
	vm.removeTranslation = removeTranslation;

	vm.oldTranslationKeyDown = oldTranslationKeyDown;
	vm.oldTranslationBlurred = oldTranslationBlurred;

	return vm;

	/**
	 * Event callback when old translation is double clicked.
	 * 
	 * It shows an input field instead of plain text so that user has a
	 * feedback of new ability to change it's value.
	 */
	function editTranslation($event) {

	    currentTranslationRow.div = $event.currentTarget.children[0];
	    currentTranslationRow.input = currentTranslationRow.div.nextElementSibling;

	    var source = currentTranslationRow.input.form.getAttribute('data-source');
	    currentTranslationRow.submitBtn = submitBtns[source];

	    showCurrentTranslationInput();
	}

	/**
	 * Adds newly defined translation into the table of translations.
	 * 
	 * Note that it isn't meant to be added to translations themselves, it
	 * is instead being added to a form which triggers request for
	 * translations being approved by portal admin after submitted.
	 */
	function addTranslation(source, $event) {

	    if ($event.target.nodeName === 'A') {
		$event.target = $event.target.nextElementSibling;
	    }
	    if ($event.target.form.checkValidity()) {

		var formElements = $event.target.form.elements;

		var newTranslation = {
		    key : undefined,
		    langValues : {}
		};

		for (var i = 0; i < formElements.length; ++i) {
		    var formElement = formElements.item(i);

		    if (formElement.value.length) {
			var type = formElement.getAttribute('ng-new-translation');

			if (type === 'key') {

			    newTranslation['key'] = formElement.value;

			    var keyExists = translationKeyExists(source, newTranslation.key);

			    var errMsg;
			    if (keyExists) {

				// Show it's a duplicate key
				errMsg = translate('new_translation_key_already_used');

			    } else if (newTranslation.key.trim().length === 0) {

				// Show it's an empty value
				errMsg = translate('Empty value not allowed');
			    }

			    if (typeof errMsg !== 'undefined') {

				formElement.setCustomValidity(errMsg);

				// Hide it then
				setTimeout(function() {
				    formElement.setCustomValidity('');
				}, 500);

				return;
			    }

			    formElement.setCustomValidity('');
			} else { // the type is a language now

			    var language = type;

			    if (typeof newTranslation['langValues'][language] === 'undefined')
				newTranslation['langValues'][language] = {};

			    newTranslation['langValues'][language]['value'] = formElement.value;
			    newTranslation['langValues'][language]['name'] = newTranslation.key + '[' + language + ']';
			}

			formElement.value = '';
		    }
		}

		// Now we are sure we don't need to show user any error
		$event.preventDefault();

		if (typeof vm.newTranslations[source] === 'undefined') {
		    vm.newTranslations[source] = [ newTranslation ];
		} else {
		    vm.newTranslations[source].push(newTranslation);
		}
	    }
	}

	/**
	 * Returns true if there is found any translation with specified key
	 */
	function translationKeyExists(source, key) {
	    var inputs = submitBtns[source].form.elements;

	    for (var i = 0; i < inputs.length; i += LANG_COUNT) {
		var input = inputs.item(i);

		var inputKey = input.name.replace(/\[.+$/g, '');
		if (inputKey === key)
		    return true;
	    }

	    return false;
	}

	/**
	 * Removes translation from the table of translations.
	 * 
	 * Note that it isn't meant to be removed from translations themselves,
	 * it is instead being removed from a form which triggers request for
	 * translations being approved by portal admin after submitted.
	 */
	function removeTranslation($event) {
	    
	    var keyTD = $event.target.parentElement;
	    
	    for (var i = 0; i <= LANG_COUNT; ++i) {
		keyTD = keyTD.previousElementSibling; 
	    }
	    
	    var key = keyTD.textContent.trim();

	    if (confirm(translate('confirm_translation_delete_of') + ' "' + key + '" ?') === false) {
		return;
	    }
	    
	    var target = $event.target;
	    do {
		target = target.parentElement;
		
	    } while(target.nodeName !== 'TR' && target.nodeName !== 'TBODY');
		
	    target.remove();
	}

	/**
	 * Event callback when old translation's input field has key pressed to
	 * handle enter & escape correctly.
	 * 
	 * Note that event prevents default behavior, so it does not submit the
	 * form. It instead updates the value in a nice graphical way.
	 */
	function oldTranslationKeyDown($event) {
	    if (isEnter($event)) {

		// Do not submit the form
		$event.preventDefault();

		var newValue = $event.target.value;

		// Commiting changes
		if (changeTranslationValue(newValue)) {
		    hideCurrentTranslationInput();
		} else {

		    // Perform dummy submit to show what's wrong
		    currentTranslationRow.submitBtn.click();
		}

	    } else if (isEscape($event)) {

		// Cancelling changes
		$event.target.value = getTranslationValue();
		hideCurrentTranslationInput();
	    }
	}

	/**
	 * Event callback when old translation's input field loses focus to
	 * handle it's changes
	 */
	function oldTranslationBlurred($event) {

	    var newValue = $event.target.value;

	    if (!changeTranslationValue(newValue)) {

		// Cancelling changes
		$event.target.value = getTranslationValue();
	    }

	    hideCurrentTranslationInput();
	}

	// private

	/**
	 * Shows input within current table row & hides current div
	 */
	function showCurrentTranslationInput() {

	    removeHiddenFromClassName(currentTranslationRow.input);

	    currentTranslationRow.div.setAttribute('hidden', 'hidden');

	    currentTranslationRow.input.focus();
	}

	/**
	 * Hides input within current table row & shows current div
	 */
	function hideCurrentTranslationInput() {
	    currentTranslationRow.input.className = currentTranslationRow.input.className + ' hidden';

	    currentTranslationRow.div.removeAttribute('hidden');
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
	function changeTranslationValue(value) {

	    value = value.trim();

	    if (value == '')
		return false; // Refuse empty values

	    var ins = currentTranslationRow.div.getElementsByTagName('ins');

	    if (ins.length === 0) { // There is no new value yet, create one

		var contents = currentTranslationRow.div.textContent.trim();

		if (contents === value)
		    return true;

		// Mark deleted if there was something
		if (contents.length)
		    currentTranslationRow.div.innerHTML = '<del style="color: red">' + contents + '</del><br>';

		ins = document.createElement('ins');

		ins.style.color = 'green';

		// Append new value
		currentTranslationRow.div.appendChild(ins);
	    } else { // There already is a value, change it's value

		var del = currentTranslationRow.div.getElementsByTagName('del');

		if (del.length) {
		    var previousContents = del[0].textContent.trim();

		    // If the new value is the same as the old value, just
		    // remove any graphics
		    if (previousContents === value.trim()) {
			currentTranslationRow.div.innerHTML = previousContents;

			return true;
		    }

		}

		ins = ins[0];
	    }

	    ins.textContent = value;

	    removeHiddenFromClassName(currentTranslationRow.submitBtn);
	    return true;
	}

	/**
	 * Returns true if language header within a institution identified by
	 * source does not have any translations within it.
	 * 
	 * It is detected by not having next closest sibling with ng-language
	 * attribute.
	 */
	function langWithoutTranslations(source, lang) {
	    return translationRows[source][lang].nextElementSibling.hasAttribute('ng-language');
	}

	/**
	 * Removes first occurence of 'hidden' in a className of provided
	 * domElement
	 */
	function removeHiddenFromClassName(domElement) {
	    domElement.className = domElement.className.replace(/\shidden|hidden\s?/g, '');
	}

	/**
	 * Gets the proposed value of the <ins> element within a div.
	 * 
	 * If <ins> is not found, then are returned the contents of the div.
	 */
	function getTranslationValue() {
	    var ins = currentTranslationRow.div.getElementsByTagName('ins');

	    var contents;

	    if (ins.length === 0) {
		contents = currentTranslationRow.div.textContent.trim();
	    } else {
		contents = ins[0].textContent.trim();
	    }

	    return contents;
	}

	/**
	 * Returns true, if the provided $event created based on keyDown event
	 * was initiated by key press of enter button
	 */
	function isEnter($event) {
	    return $event.keyCode === 13;
	}

	/**
	 * Returns true, if the provided $event created based on keyDown event
	 * was initiated by key press of escape button
	 */
	function isEscape($event) {
	    return $event.keyCode === 27;
	}
    }

    /**
     * Links a submit button
     */
    function submitBtn() {
	return {
	    restrict : 'A',
	    link : linker
	};

	function linker(scope, elements, attrs) {
	    var submitBtn = elements.context;

	    var source = submitBtn.form.getAttribute('data-source');

	    submitBtns[source] = submitBtn;
	}
    }

    /**
     * Links all inputs for creating new translation
     */
    function newTranslation() {
	return {
	    restrict : 'A',
	    link : linker
	};

	function linker(scope, elements, attrs) {
	    var type = attrs['ngNewTranslation'];

	    var input = elements.context;

	    var source = input.form.id.split('_')[0];

	    if (typeof newTranslationRows[source] === 'undefined')
		newTranslationRows[source] = {};

	    if (type === 'key') {
		newTranslationRows[source]['keyInput'] = input;
	    } else if (type === 'submit') {
		newTranslationRows[source]['submit'] = input;
	    } else {

		if (typeof newTranslationRows[source]['langInput'] === 'undefined')
		    newTranslationRows[source]['langInput'] = {};

		newTranslationRows[source]['langInput'][type] = input;
	    }
	}
    }

    function newTranslationTemplate() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/cpk-devel/js/ng-cpk/admin/new-translation.html'
	};
    }
})();
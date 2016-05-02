/**
 * Definition for admin module's Approval Controller handling dynamics within an
 * Approval action
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('admin').controller('TranslationsController', TranslationsController).directive('ngSubmitBtn', submitBtn).directive('ngNewTranslation',
	    newTranslation).directive('ngNewTranslationTemplate', newTranslationTemplate).directive('ngDeletedTranslationTemplate', deletedTranslationTemplate);

    TranslationsController.$inject = [ 'translateFilter' ];

    const
    LANG_COUNT = 2;

    var submitBtns = {};

    var newTranslationRows = {};

    var translationRows = {};

    function TranslationsController(translate) {

	var unsaved = false;

	var currentTranslationRow = {
	    div : undefined,
	    input : undefined,
	    submitBtn : undefined,
	    source : undefined
	};

	var vm = this;

	// note that it does not hold all the translations, just recently added
	vm.newTranslations = {};

	vm.deletedTranslations = {};

	vm.editTranslation = editTranslation;

	vm.addTranslation = addTranslation;
	vm.removeTranslation = removeTranslation;
	vm.restoreTranslation = restoreTranslation;

	vm.oldTranslationKeyDown = oldTranslationKeyDown;
	vm.oldTranslationBlurred = oldTranslationBlurred;

	vm.submit = submit;

	window.onbeforeunload = function(e) {
	    if (unsaved)
		return 'You have unsaved changes, do you really want to quit?';
	};

	return vm;

	/**
	 * Event callback when old translation is double clicked.
	 * 
	 * It shows an input field instead of plain text so that user has a
	 * feedback of new ability to change it's value.
	 */
	function editTranslation($event, type) {

	    currentTranslationRow.div = $event.currentTarget.children[0];
	    currentTranslationRow.input = currentTranslationRow.div.nextElementSibling;

	    currentTranslationRow.input.setAttribute('data-prev', currentTranslationRow.input.value);

	    if (typeof type === 'undefined') {
		var source = currentTranslationRow.input.form.getAttribute('data-source');
		currentTranslationRow.submitBtn = submitBtns[source];
	    } else if (type === 'key') {
		currentTranslationRow.submitBtn = currentTranslationRow.input.form.children[0];
		currentTranslationRow.source = currentTranslationRow.div.parentElement.nextElementSibling.lastElementChild.form.getAttribute('data-source');
	    }

	    showCurrentTranslationInput();
	}

	/**
	 * Adds newly defined translation into the table of translations.
	 * 
	 * Note that it isn't meant to be added to translations themselves, it
	 * is instead being added to a form which triggers request for
	 * translations being approved by portal admin after submitted.
	 */
	function addTranslation(source, $event, type) {

	    if (typeof type !== 'undefined') {
		if (type === 'json' && typeof source === 'string') {
		    var parsedTranslation = JSON.parse(source);

		    return addTranslation(parsedTranslation, $event, 'object');

		} else if (type === 'object' && typeof source === 'object') {

		    parsedTranslation = source;

		    source = parsedTranslation.source;

		    delete (parsedTranslation['source']);

		    var newTranslation = {
			key : parsedTranslation.key,
			langValues : []
		    };

		    delete (parsedTranslation['key']);

		    Object.keys(parsedTranslation).forEach(function(language) {

			var langValue = {
			    value : parsedTranslation[language],
			    name : newTranslation.key + '[' + language + ']'
			};

			newTranslation.langValues.push(langValue);
		    });

		    unsaved = true;

		    addNewTranslation(source, newTranslation);

		    getParentTableRow($event.target).remove();
		}
	    } else {

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

				newTranslation['key'] = formElement.value.trim();

				var keyValid = checkKeyValidity(formElement, source, newTranslation.key);

				// Exit now if key is not valid
				if (!keyValid)
				    return;
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

		    unsaved = true;

		    // Now we are sure we don't need to show user any error
		    $event.preventDefault();

		    addNewTranslation(source, newTranslation);
		}
	    }
	}

	/**
	 * Removes translation from the table of translations.
	 * 
	 * Note that it isn't meant to be removed from translations themselves,
	 * it is instead being removed from a form which triggers request for
	 * translations being approved by portal admin after submitted.
	 */
	function removeTranslation(source, $event) {

	    var tr = getParentTableRow($event.target);

	    var key = tr.children[0].textContent.trim();

	    var langValues = [];

	    var newDef = {
		key : key
	    };

	    for (var i = 1; tr.children[i].hasAttribute('ng-dblclick'); ++i) {

		var td = tr.children[i];

		var div = td.children[0];
		var ins = div.getElementsByTagName('ins');

		var langValue;
		if (ins.length) {
		    langValue = ins[0].textContent.trim();
		} else {
		    langValue = div.textContent.trim();
		}

		langValues.push(langValue);

		var inputs = td.getElementsByTagName('input');

		if (inputs.length) {

		    var input = inputs[0];

		    var lang = input.name.substr(-3, 2);

		    newDef[lang] = langValue;
		}
	    }

	    newDef['source'] = source;

	    var deletedTranslation = {
		key : key,
		langValues : langValues,
		newDef : newDef
	    };

	    var alreadyDeleted = false;
	    if (typeof vm.newTranslations[source] !== 'undefined')
		for (var i = 0; i < vm.newTranslations[source].length; ++i) {
		    if (vm.newTranslations[source][i].key.trim() === key.trim()) {
			vm.newTranslations[source].splice(i--, 1);

			alreadyDeleted = true;
		    }
		}

	    if (!alreadyDeleted)
		tr.remove();

	    addDeletedTranslation(source, deletedTranslation);

	}

	/**
	 * Basically the same function as the addTranslation except this one
	 * deletes the "deletedTranslation" from within the view model - this is
	 * useful specifically when the deleted translation was rendered by
	 * angular, not PHP.
	 */
	function restoreTranslation(key, newDefinition, $event) {
	    vm.deletedTranslations[newDefinition.source].splice(key, 1);

	    return addTranslation(newDefinition, $event, 'object');
	}

	/**
	 * Event callback when old translation's input field has key pressed to
	 * handle enter & escape correctly.
	 * 
	 * Note that event prevents default behavior, so it does not submit the
	 * form. It instead updates the value in a nice graphical way.
	 */
	function oldTranslationKeyDown($event, type) {
	    if (isEnter($event)) {

		// Do not submit the form
		$event.preventDefault();

		var newValue = $event.target.value;

		var resetWhenInvalid = false;

		// Commiting changes
		if (changeTranslationValue($event.target, newValue, resetWhenInvalid, type)) {
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
	function oldTranslationBlurred($event, type) {

	    var newValue = $event.target.value;

	    var resetWhenInvalid = true;

	    if (!changeTranslationValue($event.target, newValue, resetWhenInvalid, type)) {

		// Cancelling changes
		$event.target.value = getTranslationValue();
	    }

	    hideCurrentTranslationInput();
	}

	/**
	 * When hitting the submit button, the user is actually commiting a
	 * save, so let's make sure to don't show him the message "You have
	 * unsaved changes"
	 */
	function submit() {
	    unsaved = false;
	}

	// private

	/**
	 * Returns true if there is found any translation with specified key
	 */
	function translationKeyExists(source, key) {
	    var inputs = submitBtns[source].form.elements;

	    for (var i = 0; i < inputs.length; i += LANG_COUNT) {
		var input = inputs.item(i);

		var inputKey = getKeyFromInputName(input.name);
		if (inputKey === key.trim())
		    return true;
	    }

	    return false;
	}

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
	 * Returns closest parent
	 * <tr> element if any. Undefined otherwise.
	 */
	function getParentTableRow(target) {
	    do {
		target = target.parentElement;

	    } while (target.nodeName !== 'TR' && target.nodeName !== 'BODY');

	    if (target.nodeName !== 'TR') {
		return undefined;
	    }

	    return target;
	}

	/**
	 * Retrieves the key from an input name
	 */
	function getKeyFromInputName(inputName) {
	    return inputName.replace(/\[.+$/g, '');
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
	function changeTranslationValue(input, value, resetWhenInvalid, type) {

	    if (typeof resetWhenInvalid === 'undefined') {
		resetWhenInvalid = false;
	    }

	    if (typeof type === 'undefined') {
		type = 'languageTranslationValue';
	    }

	    value = value.trim();

	    if (value == '') {

		var errMsg = translate('empty_value_not_allowed');

		if (resetWhenInvalid === false) {
		    setTmpCustomValidity(input, errMsg);
		} else {
		    input.value = input.getAttribute('data-prev');
		}

		return false; // Refuse empty values
	    } else if (type === 'key') {

		var keyValid = checkKeyValidity(input, currentTranslationRow.source, value, resetWhenInvalid);

		// Exit now if key is not valid
		if (! keyValid)
		    return false;
	    }

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

	    unsaved = true;
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
	 * Adds new translation into the view model
	 */
	function addNewTranslation(source, newTranslation) {
	    if (typeof vm.newTranslations[source] === 'undefined') {
		vm.newTranslations[source] = [ newTranslation ];
	    } else
		vm.newTranslations[source].push(newTranslation);
	}

	/**
	 * Add deleted translation into the view model
	 */
	function addDeletedTranslation(source, deletedTranslation) {

	    if (typeof vm.deletedTranslations[source] === 'undefined') {
		vm.deletedTranslations[source] = [ deletedTranslation ];
	    } else
		vm.deletedTranslations[source].push(deletedTranslation);
	}

	/**
	 * Checks validity of a translation key. Returns false if invalid with
	 * error message setup via setCustomValidity on the input dom element
	 */
	function checkKeyValidity(input, source, key, resetWhenInvalid) {

	    if (typeof resetWhenInvalid === 'undefined') {
		resetWhenInvalid = false;
	    }

	    var keyExists = translationKeyExists(source, key);

	    var errMsg;
	    if (keyExists) {

		// Show it's a duplicate key
		errMsg = translate('new_translation_key_already_used');

	    } else if (key.length === 0) {

		// Show it's an empty value
		errMsg = translate('empty_value_not_allowed');
	    } else if (key.match(/\s+/) !== null) {

		errMsg = translate('whitespaces_not_allowed');
	    }

	    if (typeof errMsg !== 'undefined') {

		if (resetWhenInvalid === false) {
		    // Just show what is invalid
		    setTmpCustomValidity(input, errMsg);

		} else {
		    // Just reset to a previous value
		    input.value = input.getAttribute('data-prev');
		}

		return false;
	    }

	    input.setCustomValidity('');
	    return true;
	}

	/**
	 * Sets temporary custom validity, usually being used in order to only
	 * show user what is invalid & enable exiting the edit mode with form
	 * still being submit capable
	 */
	function setTmpCustomValidity(input, validity) {
	    input.setCustomValidity(validity);

	    setTimeout(function() {
		input.setCustomValidity('');
	    }, 500);
	}

	/**
	 * Gets the proposed value of the <ins> element within a currently
	 * editing div.
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

	    var source = input.getAttribute('form').split('_')[0];

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
	    templateUrl : '/themes/cpk-devel/js/ng-cpk/admin/translations/new-translation.html',
	    link : linker
	};

	function linker(scope, elements, attrs) {
	    var source = attrs['ngNewTranslationTemplate'];

	    scope.source = source;
	}
    }

    function deletedTranslationTemplate() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/cpk-devel/js/ng-cpk/admin/translations/deleted-translation.html'
	}
    }
})();
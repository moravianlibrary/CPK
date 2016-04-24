/**
 * Definition for admin module's Approval Controller handling dynamics within an
 * Approval action
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('admin').controller('TranslationsController', TranslationsController).directive('ngSubmitBtn', submitBtn).directive('ngNewTranslation',
	    newTranslation).directive('ngNewTranslationTemplate', newTranslationTemplate).directive('ngLanguage', languageDirective);

    TranslationsController.$inject = [ 'translateFilter' ];

    var submitBtns = {};

    var newTranslationRows = {};

    var translationRows = {};

    function TranslationsController(translate) {

	var editedAt = undefined;

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

	    editedAt = (new Date()).getTime();
	}

	/**
	 * Adds newly defined translation into the table of translations.
	 * 
	 * Note that it isn't meant to be added to translations themselves, it
	 * is instead being added to a form which triggers request for
	 * translations being approved by portal admin after submitted.
	 */
	function addTranslation(source, $event) {
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

			if (type !== 'key') {
			    newTranslation['langValues'][type] = formElement.value;
			} else {
			    newTranslation['key'] = formElement.value;

			    var keyExists = getTranslationRowWithKey(source, newTranslation.key);

			    if (keyExists) {

				// Show it's a duplicate key
				var errMsg = translate('new_translation_key_already_used');
				formElement.setCustomValidity(errMsg);

				// Hide it then
				setTimeout(function() {
				    formElement.setCustomValidity('');
				}, 500);
				return;
			    } else {
				formElement.setCustomValidity('');
			    }
			}

			formElement.value = '';
		    }
		}

		// Now we are sure we don't need to show user any error
		$event.preventDefault();

		var languages = Object.keys(newTranslation.langValues);

		var languageTranslations = {};

		languages.forEach(function(language) {

		    languageTranslations[language] = [];

		    var value = newTranslation.langValues[language];
		    languageTranslations[language].push({
			key : newTranslation.key,
			value : value,
			name : language + '[' + newTranslation.key + ']'
		    });
		});

		if (typeof vm.newTranslations[source] === 'undefined') {
		    vm.newTranslations[source] = languageTranslations;
		} else {

		    // Merge the institution translations

		    var newSourceTranslations = {};
		    Object.keys(vm.newTranslations[source]).forEach(function(language) {

			var oldSourceTranslations = vm.newTranslations[source][language];

			newSourceTranslations[language] = languageTranslations[language];

			newSourceTranslations[language] = newSourceTranslations[language].concat(oldSourceTranslations);
		    });

		    vm.newTranslations[source] = newSourceTranslations;
		}

		showLanguagesHeaders(source);
	    }
	}

	/**
	 * Removes translation from the table of translations.
	 * 
	 * Note that it isn't meant to be removed from translations themselves,
	 * it is instead being removed from a form which triggers request for
	 * translations being approved by portal admin after submitted.
	 */
	function removeTranslation($event) {
	    // Get the hidden input from the same table data cell
	    var input = $event.target.parentElement.nextElementSibling;

	    // Get the key from previous table data cell
	    var key = input.parentElement.previousElementSibling.textContent.trim();

	    if (confirm(translate('confirm_translation_delete_of') + ' "' + key + '" ?') === false) {
		return;
	    }

	    var source = input.form.getAttribute('data-source');

	    removeTranslationKey(source, key);
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
	 * Removes key translated within an institution identified by source in
	 * all languages
	 */
	function removeTranslationKey(source, key) {
	    var languages = Object.keys(translationRows[source]);

	    languages.forEach(function(lang) {
		var tr = getTranslationRowWithKey(source, key, lang);

		if (tr !== false) {
		    tr.remove();

		    // Hide language header if has no translations
		    if (langWithoutTranslations(source, lang)) {
			translationRows[source][lang].className += ' hidden';
		    }
		}
	    });
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
			del[0].parentElement.innerHTML = previousContents;
			return true;
		    }

		}

		ins = ins[0];
	    }

	    ins.textContent = value;

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
	 * Unhides all the language header rows from within the institution
	 * table
	 */
	function showLanguagesHeaders(source) {

	    var languageRows = translationRows[source];

	    Object.keys(languageRows).forEach(function(language) {
		removeHiddenFromClassName(languageRows[language]);
	    });
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
	 * Retrieves the table row domElement which is identified by key it
	 * translates.
	 * 
	 * Returns false if not found.
	 * 
	 * There may be also left the language attribute empty. Then it returns
	 * false only if no language has provided key. Otherwise returns first
	 * translationRow found.
	 */
	function getTranslationRowWithKey(source, key, lang) {

	    if (typeof lang !== 'undefined') {

		var languageRow = translationRows[source][lang];

		var next = languageRow.nextElementSibling;

		while (next.hasAttribute('ng-language') === false) {
		    if (next.children[0].textContent.trim() === key)
			return next;

		    next = next.nextElementSibling;
		}
	    } else {
		var languages = Object.keys(translationRows[source]);

		for (var i = 0; i < languages.length; ++i) {
		    var lang = languages[i];

		    var result = getTranslationRowWithKey(source, key, lang);
		    if (result !== false) {
			return result;
		    }
		}
		;
	    }

	    return false;
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

    function languageDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};

	function linker(scope, elements, attrs) {

	    var attributeVal = attrs['ngLanguage'];

	    if (attributeVal === 'newTransDef') {
		return;
		/*
		 * It's here to easily detect if language has any translations
		 * within it by not having the next one with attribute
		 * 'ng-language'
		 */

	    }

	    var source, lang;

	    [ source, lang ] = attrs['ngLanguage'].split('_');

	    if (typeof translationRows[source] === 'undefined')
		translationRows[source] = {};

	    translationRows[source][lang] = elements.context;
	}
    }
})();
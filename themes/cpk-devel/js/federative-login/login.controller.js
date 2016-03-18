/**
 * Federative Login controller.
 * 
 * Uses localstorage to store information about last used identity providers.
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('federativeLogin').controller('FederativeLoginController', FederativeLoginController).directive('ngLastUsed', lastUsedDirective).directive(
	    'ngHelpContent', helpContentDirective);

    FederativeLoginController.$inject = [ '$log' ];

    var DOMholder = {
	lastUsed : undefined
    }

    function FederativeLoginController($log) {

	var lastIdpsTag = '__luidps', lastIdps = [], initializedLastIdps = false;

	var helperHidden = true;

	var vm = this;

	vm.login = login;

	vm.hasLastIdps = hasLastIdps;

	vm.getLastIdps = getLastIdps;

	vm.showHelpContent = showHelpContent;

	return vm;

	// Public

	function login(idp) {

	    if (typeof idp === 'string')
		idp = JSON.parse(idp);

	    getLastIdps();

	    // If saved already, just push it in front
	    lastIdps.find(function(lastIdp, i) {
		if (lastIdp.href === idp.href) {

		    // Remove yourself
		    lastIdps.splice(i, 1);
		    return true;
		}
	    });

	    // Set as first
	    lastIdps.unshift(idp);

	    // Maximally we will have 3 institutions
	    if (lastIdps.length > 3)
		lastIdps.pop();

	    var source = JSON.stringify(lastIdps);

	    localStorage.setItem(lastIdpsTag, source);

	    window.location = idp.href;
	}

	function hasLastIdps() {

	    getLastIdps();

	    return lastIdps !== null && lastIdps instanceof Array && lastIdps.length !== 0;
	}

	function getLastIdps() {
	    if (initializedLastIdps === false) {
		initializeLastIdps();
	    }

	    return lastIdps;
	}

	function showHelpContent() {
	    if (helperHidden) {
		DOMholder.helpContent.removeAttribute('hidden');
	    } else {
		DOMholder.helpContent.setAttribute('hidden', 'hidden');
	    }
	    helperHidden = !helperHidden;
	}

	// Private

	function initializeLastIdps() {
	    lastIdps = localStorage.getItem(lastIdpsTag);

	    if (lastIdps === null) {
		lastIdps = [];
	    } else {
		try {
		    lastIdps = JSON.parse(lastIdps);
		} catch (e) {
		    $log.error('Could not parse lastIdps from localStorage', e);
		    lastIdps = [];
		}

		// Setup default language
		var lang = document.body.parentNode.getAttribute('lang');

		lastIdps.forEach(function(lastIdp) {
		    lastIdp.name = lastIdp['name_' + lang];
		});
	    }

	    initializedLastIdps = true;
	}
    }

    function lastUsedDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/cpk-devel/js/federative-login/last-used.html'
	};
    }

    function helpContentDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};

	function linker(scope, elements, attrs) {

	    DOMholder.helpContent = elements.context;
	}
    }
})();
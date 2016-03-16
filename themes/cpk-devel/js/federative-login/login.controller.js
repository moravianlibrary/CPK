/**
 * Federative Login controller.
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('federativeLogin').controller('FederativeLoginController', FederativeLoginController).directive('ngLastUsed', lastUsed);

    FederativeLoginController.$inject = [ '$log' ];

    var DOMholder = {
	'lastUsed' : undefined
    }

    function FederativeLoginController($log) {

	var lastIdpsTag = '__luidps', lastIdps = [], initializedLastIdps = false;

	var vm = this;

	vm.login = login;

	vm.hasLastIdps = hasLastIdps;

	vm.getLastIdps = getLastIdps;

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
	    }

	    // Setup default language
	    var lang = document.body.parentNode.getAttribute('lang');

	    lastIdps.forEach(function(lastIdp) {
		lastIdp.name = lastIdp['name_' + lang];
	    });

	    initializedLastIdps = true;
	}
    }

    function lastUsed() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/cpk-devel/js/federative-login/last-used.html',
	    link : linker
	};

	function linker(scope, elements, attrs) {

	    DOMholder.lastUsed = elements.context;
	}
    }
})();
/*global VuFind*/
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
	    if (!idp.isConsolidation) {
			var action = 'library';
			if (idp.name == "MojeID | Google+ | Facebook | LinkedIn")
				action = 'social';
			dataLayer.push({
				'event': 'action.login',
				'actionContext': {
					'eventCategory': 'login',
					'eventAction': action,
					'eventLabel': idp.name,
					'eventValue': undefined,
					'nonInteraction': false
				}
			});

		getLastIdps();
		
		// IE 11 :(
		var lastIdpsLength = lastIdps.length;

		// If saved already, just push it in front
		for (var i = 0; i < lastIdpsLength; ++i) {
		    var lastIdp = lastIdps[i];
		    if (lastIdp.name === idp.name) {

			// Remove yourself
			lastIdps.splice(i, 1);
			break;
		    }
		};

		// Set as first
		lastIdps.unshift(idp);

		// Maximally we will have 3 institutions
		if (lastIdps.length > 3)
		    lastIdps.pop();

		var source = JSON.stringify(lastIdps);

		localStorage.setItem(lastIdpsTag, source);
	    } else {
			dataLayer.push({
				'event': 'action.account',
				'actionContext': {
					'eventCategory': 'account',
					'eventAction': 'connectedAccount',
					'eventLabel': idp.name,
					'eventValue': undefined,
					'nonInteraction': false
				}
			});
		};

	    if (idp.warn_msg) {
            alert(VuFind.translate('warning_safety_login'))
        }

        idp.href = updateTargetLocation(idp.href);

        window.location.replace(idp.href);
	}

	function updateTargetLocation(url) {
        let oldHref = url;
        let oldQuery = oldHref.split('?', 2)[1];
        let newQuery = new URLSearchParams(oldQuery);
        newQuery.delete('target');

        let newTarget = new URLSearchParams(location.search);
        newTarget.append('auth_method', 'Shibboleth');
        newTarget = location.protocol + '//' + location.hostname + ':' + location.port
			+ location.pathname + '?' + newTarget.toString();

        newQuery.append('target', newTarget);

        let newHref = oldHref.split('?', 2)[0] + '?' + newQuery.toString();

        return newHref;
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

		var newTarget = location.pathname + location.search;
		newTarget += (newTarget.indexOf('?') >= 0 ? '&' : '?') + 'auth_method=Shibboleth';

		lastIdps.forEach(function(lastIdp) {
		    lastIdp.name = lastIdp['name_' + lang];

		    lastIdp.href = lastIdp.href.replace(/target=[^&]*/, 'target=' + encodeURIComponent(newTarget));
		});
	    }

	    initializedLastIdps = true;
	}
    }

    function lastUsedDirective() {
	return {
	    restrict : 'A',
	    templateUrl : '/themes/bootstrap3/js/ng-cpk/federative-login/last-used.html'
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

(function() {

    angular.module('cpk').controller('GlobalController', GlobalController).directive('ngModal', ngModal);

    GlobalController.$inject = [ 'favsBroadcaster', '$rootScope', '$location', '$log', '$http' ];

    var linkedObjects = {
	modal : {
	    global : {
		body : undefined,
		header : undefined
	    }
	}
    };

    var linkerCache = {
	modal : {}
    };
    
    var jQueryModal = undefined;

    /**
     * Initialize injected favsBroadcaster & show requested modal
     */
    function GlobalController(favsBroadcaster, $rootScope, $location, $log, $http) {

	var vm = this;

	vm.getParams = $location.search();

	if (typeof vm.getParams['viewModal'] !== 'undefined') {

	    viewModal(vm.getParams['viewModal']);
	}

	$rootScope.$on('notificationClicked', notificationClicked);

	return vm;

	/**
	 * Handle click on notification
	 */
	function notificationClicked() {
	    var oldModal = vm.getParams['viewModal'];

	    vm.getParams = $location.search();

	    if (typeof vm.getParams['viewModal'] !== 'undefined') {
		
		viewModal(vm.getParams['viewModal'], $log, $http);
	    }
	}

	/*
	 * Private
	 */

	function viewModal(portalPageId) {

	    onLinkedModal('global', showTheModal);

	    function showTheModal() {

		new Promise(function() {
		    modal(true);
		});

		var header = linkedObjects.modal.global.header;
		var body = linkedObjects.modal.global.body;

		$http.get('/AJAX/JSON?method=getPortalPage&prettyUrl=' + portalPageId).then(function(dataOnSuccess) {
		    var portalPage = dataOnSuccess.data.data;

		    header.textContent = portalPage.title;
		    body.innerHTML = portalPage.content;

		}, function(dataOnError) {
		    $log.error(dataOnError);
		});

	    }
	}
	
	function modal(show) {
	    if (typeof jQueryModal === 'undefined') {
		jQueryModal = $('#modal');
	    }
	    
	    if (typeof show === 'boolean' && show)
		jQueryModal.modal('show');
	    else
		jQueryModal.modal('hide');
	    
	    return jQueryModal;
	}
    }

    function ngModal() {
	return {
	    restrict : 'A',
	    link : linker
	};

	function linker(scope, elements, attrs) {

	    // IE 11 :(
	    var modalAttr = attrs.ngModal.split('.');
	    
	    var modalId = modalAttr[0];
	    var modalPart = modalAttr[1];

	    if (typeof linkedObjects.modal[modalId] === 'undefined')
		linkedObjects.modal[modalId] = {};

	    linkedObjects.modal[modalId][modalPart] = elements.context;

	    onLinkedModal(modalId, modalPart);
	}
    }

    /**
     * Handles calling the callback function appropriate to a modalId after are
     * linked all the neccessarry modal attributes.
     * 
     * The callback must be set by onLinkedModal(modalId, callback)
     */
    function onLinkedModal(modalId, what) {
	if (typeof linkerCache.modal[modalId] === 'undefined') {
	    linkerCache.modal[modalId] = {};
	}

	if (typeof what === 'function') {
	    // Process the function as input
	    var linkerDone = linkerCache.modal[modalId].linkerDone;

	    if (typeof linkerDone === 'boolean' && linkerDone) {
		what.call();
	    } else {
		linkerCache.modal[modalId].callback = what;
	    }

	} else {
	    // Now the linker linked something

	    var callIt = true;

	    var modalAttributes = Object.keys(linkedObjects.modal[modalId]);
	    var modalAttributesLength = modalAttributes.length;

	    for (var i = 0; i < modalAttributesLength; ++i) {

		var key = modalAttributes[i];

		if (typeof linkedObjects.modal[modalId][key] === 'undefined') {
		    callIt = false;
		    break;
		}
	    }

	    if (callIt) {
		
		linkerCache.modal[modalId].linkerDone = true;
		
		if (typeof linkerCache.modal[modalId].callback === 'function') {
		    linkerCache.modal[modalId].callback.call();
		}
	    }

	}
    }
})();
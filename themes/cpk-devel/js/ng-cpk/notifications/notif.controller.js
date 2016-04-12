/**
 * Main notifications controller with two directives:
 * 
 * globalNotif & institutionNotif - check their comments
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {

    angular.module('notifications').controller('NotificationsController', NotificationsController).directive('globalNotif', globalNotifDirective).directive('institutionNotif', institutionNotif);

    NotificationsController.$inject = [ '$q', '$log', '$http' ];
    
    globalNotifDirective.$inject = [ '$log' ];
    
    /**
     * Holds DOM elements of global notifications section
     */
    var globalNotifHolder = {
	    loader : undefined,
	    withoutNotifications : undefined,
	    synchronousNotifications : undefined,
	    warningIcon : undefined
    };
    
    /**
     * Holds DOM elements of "Loading ..." for each institution user is
     * connected with.
     */
    var institutionNotifLoaderHolder = {};
    
    /**
     * Is called after linker has done it's job which is defined as done right
     * after all globalNotifHolder's object keys are filled with values
     */
    var onLinkerDone = function() {};

    function NotificationsController($q, $log, $http) {

	var vm = this;
	
	vm.notifications = {};
	
	vm.initNotifications = initNotifications;
	
	vm.notifClicked = notifClicked;
	
	onLinkerDone = function() {
	    if (! hasGlobalNotifications()) {
		showWithoutNotifications();
	    } else {
		showWarningIcon();
	    }
	}

	return vm;
	
	/**
	 * Initializes an empty array for an username provided in order to
	 * successfully bind data to this Controller
	 */
	function initNotifications(source, username) {
	    
	    vm.notifications[username] = [];
	    
	    $q.resolve(fetchNotifications(username)).then(function(notifications) {
		
		onGotNotifications(notifications, source, username);
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
	}
	
	/**
	 * What to do with notifications after we got them?
	 */
	function onGotNotifications(notifications, source, username) {
	    if (notifications instanceof Array) {
		
		vm.notifications[username] = notifications;
		    
		if (notifications.length !== 0 || hasGlobalNotifications()) {
		    showWarningIcon();
		}
		    
		hideLoader(source);
	    }
	}
	
	/**
	 * A notification has been clicked .. follow the href if any
	 */
	function notifClicked(href) {
	    
	    if (typeof href !== 'undefined') {
		
		function followLocation() {
		    
		    window.location = href;
		}
			
		var data = {
			notificationType : href.split('/').pop()
		};
			
		var options = {
			headers: {
			    'Content-Type': 'application/x-www-form-urlencoded'
			}
		};
		    
		$http.post('/AJAX/JSON?method=notificationRead', $.param(data), options).then(followLocation);
	    }
	}
	
	// Private
	
	/**
	 * Fetches notifications for provided username asynchronously.
	 * 
	 * Returns an Promise.
	 * 
	 * @param username
	 */
	function fetchNotifications(username) {
	    return new Promise(function(resolve, reject) {
		
		var data = {
			cat_username : username
		};
		
		var options = {
			headers: {
			    'Content-Type': 'application/x-www-form-urlencoded'
			}
		};
		
		$http.post('/AJAX/JSON?method=getMyNotifications', $.param(data), options).then(onSuccess, onFail);
		
		function onSuccess(response) {
		    
		    response = response.data.data;
		    
		    // Print errors if any
		    if (typeof response.errors === 'object') {
			
			response.errors.forEach(function(err) {
			    $log.error(err);
			})
		    }
		    
		    if (typeof response.notifications !== 'undefined') {
			resolve(response.notifications);
		    } else {
			reject('No notifications returned!');
		    }
		};
		
		function onFail(err) {
		    reject(err);
		}
	    });
	}
	
	/**
	 * Hides a loader for an institution.
	 * 
	 * It hides a loader associated with portal notifications if no source
	 * provided.
	 * 
	 * @param source
	 */
	function hideLoader(source) {
	    
	    if (typeof source === 'undefined') {
		globalNotifHolder.loader.setAttribute('hidden', 'hidden');
	    } else {
		institutionNotifLoaderHolder[source].setAttribute('hidden', 'hidden');
	    }
	    
	    if (! hasGlobalNotifications()) {
		
		showWithoutNotifications();
	    }
	}
	
	/**
	 * Shows up a previously hidden loader for an institution.
	 * 
	 * It shows up a loader associated with portal notifications if no
	 * source provided.
	 * 
	 * @param source
	 */
	function showLoader(source) {
	    
	    if (typeof source === 'undefined') {
		globalNotifHolder.loader.removeAttribute('hidden');
	    } else {
		institutionNotifLoaderHolder[source].removeAttribute('hidden');
	    }
	    
	    if (hasGlobalNotifications()) {

		hideWithoutNotifications();
	    }
	}

	/**
	 * Shows warning icon by setting DOM element's style to nothing. This is
	 * because how "hideWarningIcon" function works
	 */
	function showWarningIcon() {
	    globalNotifHolder.warningIcon.style = "";
	}
	
	/**
	 * Hides warning icon.
	 * 
	 * Overrides the ".fa" class by setting explicit style as setting
	 * attribute hidden would have failed hiding it.
	 */
	function hideWarningIcon() {
	    globalNotifHolder.warningIcon.style = "display: none;";
	}
	
	/**
	 * Shows up the div called "withoutNotifications" whose purpose is to
	 * inform user about having no notifications or messages within an
	 * institution identity.
	 */
	function showWithoutNotifications() {
	    globalNotifHolder.withoutNotifications.removeAttribute('hidden');
	}
	
	/**
	 * Hides that div
	 */
	function hideWithoutNotifications() {
	    globalNotifHolder.withoutNotifications.setAttribute('hidden', 'hidden');
	}
	
	/**
	 * Simply checks whether there currently are any global notifications.
	 * 
	 * @returns {Boolean}
	 */
	function hasGlobalNotifications() {
	    return globalNotifHolder.synchronousNotifications.children.length !== 0;
	}
    }
    
    /**
     * Hooks DOM elements to an variable associated with notifications linked
     * with the portal, not the institutions within it.
     */
    function globalNotifDirective($log) {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	var buf = undefined;
	
	function linker(scope, elements, attrs) {
	    // Assing the loader to the 'local' variable
	    switch (attrs.globalNotif) {
	    	case 'loader':
	    	    
	    	    globalNotifHolder.loader = elements.context;
	    	    break;
	    	    
	    	case 'withoutNotifications':
	    	    
	    	    globalNotifHolder.withoutNotifications = elements.context;
	    	    break;
	    	    
	    	case 'synchronousNotifications':
	    	    
	    	    globalNotifHolder.synchronousNotifications = elements.context;
	    	    break;
	    	    
	    	case 'warningIcon':
	    	    
	    	    globalNotifHolder.warningIcon = elements.context;
	    	    break;
	    	    
	    	default:
	    	    console.error('Linker for notifications controller failed to link global notifications element');
	    }
	    
	    checkLinkerIsDone();
	}
    }
    
    /**
     * Checks if the linker is done linking by checking variables within a
     * "globalNotifHolder" variable are all set to some value.
     * 
     * it calls "onLinkerDone" function if it is done.
     */
    function checkLinkerIsDone() {
	if (typeof buf === 'undefined') {
	    buf = {};
	    buf['globalNotifHolderKeys'] = Object.keys(globalNotifHolder);
	    buf['globalNotifHolderKeysLength'] = buf['globalNotifHolderKeys'].length;
	}
	
	for (var i = 0; i < buf['globalNotifHolderKeysLength'];) {
		
	    if (typeof globalNotifHolder[buf['globalNotifHolderKeys'][i]] === 'undefined')
		break;
		
	    if (++i === buf['globalNotifHolderKeysLength']) {
		if (typeof onLinkerDone === 'function')
		    onLinkerDone();
		else
		    $log.error('onLinkerDone must be a function');
	    }
	}
    }
    
    /**
     * Hooks DOM elements to an variable associated with particular institution
     * identity.
     */
    function institutionNotif() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    
	    var source = attrs.institutionNotif;
	    
	    // Now we really need to hook only the warning icons to each
	    institutionNotifLoaderHolder[source] = elements.context;
	}
    }
})();
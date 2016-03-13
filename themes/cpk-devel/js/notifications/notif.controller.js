/**
 * Main notifications controller
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
    
    var institutionNotifWarningIconHolder = {};
    
    /**
     * Is called after linker has done it's job
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
		
		if (notifications instanceof Array) {
		    
		    vm.notifications[username] = notifications;
		    
		    if (notifications.length !== 0 || hasGlobalNotifications()) {
			showWarningIcon();
		    }
		    
		    hideLoader(source);
		}
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
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
	
	function hideLoader(source) {
	    
	    if (typeof source === 'undefined') {
		globalNotifHolder.loader.setAttribute('hidden', 'hidden');
	    } else {
		institutionNotifWarningIconHolder[source].setAttribute('hidden', 'hidden');
	    }
	    
	    // If there is no global notification, show 'no notifications
	    // notification' :D
	    if (! hasGlobalNotifications()) {
		
		showWithoutNotifications();
	    }
	}
	
	function showLoader(source) {
	    
	    if (typeof source === 'undefined') {
		globalNotifHolder.loader.removeAttribute('hidden');
	    } else {
		institutionNotifWarningIconHolder[source].removeAttribute('hidden');
	    }
	    
	    // If there is any global notification, hide 'no notifications
	    // notification' :)
	    if (hasGlobalNotifications()) {

		hideWithoutNotifications();
	    }
	}
	
	function showWarningIcon() {
	    globalNotifHolder.warningIcon.style = "";
	}
	
	function hideWarningIcon() {
	    // We need to override the ".fa" class
	    globalNotifHolder.warningIcon.style = "display: none;";
	}
	
	function showWithoutNotifications() {
	    globalNotifHolder.withoutNotifications.removeAttribute('hidden');
	}
	
	function hideWithoutNotifications() {
	    globalNotifHolder.withoutNotifications.setAttribute('hidden', 'hidden');
	}
	
	function hasGlobalNotifications() {
	    return globalNotifHolder.synchronousNotifications.children.length !== 0;
	}
    }
    
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
	    
	    if (typeof buf === 'undefined') {
		buf = {};
		buf['globalNotifHolderKeys'] = Object.keys(globalNotifHolder);
		buf['globalNotifHolderKeysLength'] = buf['globalNotifHolderKeys'].length;
	    }
	    
	    // Are we done linking ?
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
    }
    
    function institutionNotif() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
	function linker(scope, elements, attrs) {
	    
	    var source = attrs.institutionNotif;
	    
	    institutionNotifWarningIconHolder[source] = elements.context;
	}
    }
})();
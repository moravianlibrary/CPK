/**
 * Main notifications controller
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {

    angular.module('notifications').controller('NotificationsController', NotificationsController).directive('globalNotif', globalNotifDirective);

    NotificationsController.$inject = [ '$q', '$log', '$http' ];
    
    /**
     * Holds DOM elements of global notifications section
     */
    var globalNotifHolder = {
	    loader : undefined,
	    withoutNotifications : undefined,
	    synchronousNotifications : undefined
    };

    function NotificationsController($q, $log, $http) {

	var vm = this;
	
	vm.notifications = {};
	
	vm.initNotifications = initNotifications;
	
	vm.notifClicked = notifClicked;

	return vm;
	
	/**
	 * Initializes an empty array for an username provided in order to
	 * successfully bind data to this Controller
	 */
	function initNotifications(username) {
	    
	    vm.notifications[username] = [];
	    
	    $q.resolve(fetchNotifications(username)).then(function(notifications) {
		
		if (notifications instanceof Array) {
		    
		    vm.notifications[username] = notifications;
		    
		    hideLoader();
		}
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
	}
	
	/**
	 * A notification has been clicked .. follow the href if any
	 */
	function notifClicked(href) {
	    
	    if (typeof href !== 'undefined')
		window.location = href;
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
		    if (typeof response.errors !== 'undefined') {
			
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
	
	function hideLoader() {
	    globalNotifHolder.loader.setAttribute('hidden', 'hidden');
	    
	    // If there is no global notification, show 'no notifications notification' :D
	    if (globalNotifHolder.synchronousNotifications.children.length === 0) {
		
		hideWithoutNotifications();
	    }
	}
	
	function showLoader() {
	    globalNotifHolder.loader.removeAttribute('hidden');
	    
	    // If there is any global notification, hide 'no notifications notification' :)
	    if (globalNotifHolder.synchronousNotifications.children.length !== 0) {
		
		showWithoutNotifications();
	    }
	}
	
	function hideWithoutNotifications() {
	    globalNotifHolder.withoutNotifications.removeAttribute('hidden');
	}
	
	function showWithoutNotifications() {
	    globalNotifHolder.withoutNotifications.setAttribute('hidden', 'hidden');
	}
    }
    
    function globalNotifDirective() {
	return {
	    restrict : 'A',
	    link : linker
	};
	
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
	    	    
	    	default:
	    	    console.error('Linker for notifications controller failed to link global notifications element');
	    }
	}
    }
})();
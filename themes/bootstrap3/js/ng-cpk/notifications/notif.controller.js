/**
 * Main notifications controller with two directives:
 * 
 * globalNotif & institutionNotif - check their comments
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {

    angular.module('notifications').controller('NotificationsController', NotificationsController).directive('globalNotif', globalNotifDirective).directive('institutionNotif', institutionNotif);

    NotificationsController.$inject = [ '$q', '$log', '$http', '$location', '$rootScope' ];
    
    globalNotifDirective.$inject = [ '$log' ];
    
    /**
     * Holds DOM elements of global notifications section
     */
    var globalNotifHolder = {
	    loader : undefined,
	    parent : undefined,
	    synchronousNotifications : undefined,
	    warningIcon : undefined,
	    unreadNotifsCount : undefined
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

    function NotificationsController($q, $log, $http, $location, $rootScope) {
	
	var apiNonrelevantJobDoneFlag = false;
	
	var onApiNonrelevantJobDone;
	
	var unreadNotifsCount = 0;

	var vm = this;
	
	vm.notifications = {};
	
	vm.initApiRelevantNotificationsForUserCard = initApiRelevantNotificationsForUserCard;
	
	vm.initApiNonrelevantNotifications = initApiNonrelevantNotifications;
	
	vm.notifClicked = notifClicked;
	
	onLinkerDone = function() {
	    if (! hasGlobalNotifications()) {
		
		if (apiNonrelevantJobDoneFlag) {
		    hideGlobalNotifications();
		} else {
		    onApiNonrelevantJobDone = onLinkerDone;
		}
	    } else {
		showWarningIcon();
	    }
	}

	return vm;
	
	/**
	 * Initializes an empty array for an username provided in order to
	 * successfully bind data to this Controller
	 */
	function initApiRelevantNotificationsForUserCard(source, username) {
	    
	    vm.notifications[username] = [];
	    
	    $q.resolve(fetchNotificationsForUserCard(username)).then(function(notifications) {
		
		onGotNotificationsForUserCard(notifications, source, username);
		
	    }).catch(function(reason) {
		
		$log.error(reason);
	    });
	}
	
	function initApiNonrelevantNotifications() {
	    
	    vm.notifications['noAPI'] = {};
	    
	    vm.notifications['noAPI']['user'] = [];

	    $q.resolve(fetchNotificationsForUser()).then(function(notifications) {
		
		onGotNotificationsForUser(notifications);
		
		apiNonrelevantJobDone();
		
	    }).catch(function(reason) {
		
		$log.error(reason);
		
		apiNonrelevantJobDone();
	    });
	}
	
	/**
	 * Process the notifications on user_card scale after we got them.
	 */
	function onGotNotificationsForUserCard(notifications, source, username) {
	    if (notifications instanceof Array) {
		
		vm.notifications[username] = notifications;
		    
		if (notifications.length !== 0) {
		    
		    notifications.forEach(function(notification) {
			
			if (notification.clazz.match(/unread/))
			   ++unreadNotifsCount;
		    });
		    
		    updateUnreadNotifsCount();
		    
		    showWarningIcon();
		} else {
		    hideDOM(institutionNotifLoaderHolder[source + '.parent']);
		}
		    
		hideLoader(source);
	    }
	}
	
	/**
	 * Process the notifications on user scale after we got them.
	 */
	function onGotNotificationsForUser(notifications) {
	    if (notifications instanceof Array) {
		
		vm.notifications['noAPI']['user'] = notifications;
		    
		if (notifications.length !== 0) {
		    
		    notifications.forEach(function(notification) {
			
			if (notification.clazz.match(/unread/))
			   ++unreadNotifsCount;
		    });
		    
		    updateUnreadNotifsCount();
		    
		    showWarningIcon();
		}
	    }
	}
	
	/**
	 * A notification has been clicked .. follow the href if any
	 */
	function notifClicked(notification, source) {
	    
	    var clazz = notification.clazz;
	    var href = notification.href;
	    var type = notification.type;
	    
	    if (clazz.match(/unread/)) {
		--unreadNotifsCount;
		updateUnreadNotifsCount();
		
		var searchFor = /[^\s]*unread/;
		
		notification.clazz = clazz.replace(searchFor, '');
	    }
	    
	    if (typeof href !== 'undefined') {
		
		function followLocation() {
		    
		    if (source === 'user') {
			$location.url(href);
		    } else {
			location.href = href;
		    }
		    
		    $rootScope.$broadcast('notificationClicked');
		}
			
		var data = {
			notificationType : type,
			source : source
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
	function fetchNotificationsForUserCard(username) {
	    return new Promise(function(resolve, reject) {
		
		var data = {
			cat_username : username
		};
		
		var options = {
			headers: {
			    'Content-Type': 'application/x-www-form-urlencoded'
			}
		};
		
		$http.post('/AJAX/JSON?method=getMyNotificationsForUserCard', $.param(data), options).then(onSuccess, onFail);
		
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
			    if (response.notifications.length == 0) {
			        $("ul#notificationsList>li#" + response.source).append("<div class=\"notif-default\">" + VuFind.translate('without_notifications') + "</div>");
			    }
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
	 * Fetches notifications for current user asynchronously.
	 * 
	 * Returns an Promise.
	 * 
	 * @param username
	 */
	function fetchNotificationsForUser() {
	    return new Promise(function(resolve, reject) {
		$http({
			method: 'GET',
			url: '/AJAX/JSON?method=getMyNotificationsForUser'
		    }).then(onSuccess, onFail);
		
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
		}
		
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
		
		hideGlobalNotifications();
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

		showGlobalNotifications();
	    }
	}

	/**
	 * Shows warning icon by setting DOM element's style to nothing. This is
	 * because how "hideWarningIcon" function works
	 */
	function showWarningIcon() {
	    globalNotifHolder.warningIcon.style.display = "";
	}
	
	/**
	 * Hides warning icon.
	 * 
	 * Overrides the ".fa" class by setting explicit style as setting
	 * attribute hidden would have failed hiding it.
	 */
	function hideWarningIcon() {
	    globalNotifHolder.warningIcon.style.display = "none";
	}
	
	/**
	 * Sets the count to the counter of unread notifications
	 */
	function updateUnreadNotifsCount() {
	    
	    unreadNotifsCount < 0 && (unreadNotifsCount = 0);
	    
	    globalNotifHolder.unreadNotifsCount.textContent = unreadNotifsCount || '';
	}
	
	/**
	 * Shows up the global notification section
	 */
	function showGlobalNotifications() {
	    showDOM(globalNotifHolder.parent);
	}
	
	/**
	 * Hides that div
	 */
	function hideGlobalNotifications() {
	    hideDOM(globalNotifHolder.parent);
	}
	
	function showDOM(dom) {
	    dom.removeAttribute('hidden');
	}
	
	function hideDOM(dom) {
	    dom.setAttribute('hidden', 'hidden');
	}
	
	/**
	 * Simply checks whether there currently are any global notifications.
	 * 
	 * @returns {Boolean}
	 */
	function hasGlobalNotifications() {
	    
	    var hasSynchronousGlobalNotifications = globalNotifHolder.synchronousNotifications.children.length !== 0;
	    
	    var hasApiNonrelevantNotifications = typeof vm.notifications['noAPI']['user'] === 'object' && vm.notifications['noAPI']['user'].length !== 0;
	    
	    return hasSynchronousGlobalNotifications || hasApiNonrelevantNotifications;
	}
	
	function apiNonrelevantJobDone() {
	    apiNonrelevantJobDoneFlag = true;
		
	    if (typeof onApiNonrelevantJobDone === 'function') {
	        onApiNonrelevantJobDone.call();
	    }
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
	    	    
	    	case 'parent':
	    	    
	    	    globalNotifHolder.parent = elements.context;
	    	    break;
	    	    
	    	case 'synchronousNotifications':
	    	    
	    	    globalNotifHolder.synchronousNotifications = elements.context;
	    	    break;
	    	    
	    	case 'warningIcon':
	    	    
	    	    globalNotifHolder.warningIcon = elements.context;
	    	    break;
	    	    
	    	case 'unreadNotifsCount':
	    	    
	    	    globalNotifHolder.unreadNotifsCount = elements.context;
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
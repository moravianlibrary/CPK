/**
 * 
 */
(function() {
    angular.module('favorites').factory('favsNotifications', favsNotificationsService);

    favsNotificationsService.$inject = [ 'translateFilter' ];

    function favsNotificationsService(translate) {

	// Public object
	var notifications = {
	    favAdded : favAdded,
	    allFavsRemoved : allFavsRemoved
	};

	// Private vars
	var addedSomethingAlready = false;
	var notificationsEnabled = typeof __notif !== 'undefined';

	return notifications;

	// Public methods

	function favAdded() {
	    if (notificationsEnabled === true) {
		
		if (addedSomethingAlready === false) {
		    addedSomethingAlready = true;

		    createNotificationWarning();
		}
	    }
	}

	function allFavsRemoved() {
	    if (notificationsEnabled === true) {

		// Remove the notification
		__notif.helper.pointers.global.children('.notif-favs').remove();

		// Remove the warning icon if there is no more notifs
		if (__notif.sourcesRead.unreadCount === 0) {

		    addedSomethingAlready = false;

		    // Hide warning icon ..
		    __notif.warning.hide();

		    __notif.helper.pointers.global.children().first().show();
		}
	    }
	}

	// Private methods

	function createNotificationWarning() {

	    var translatedMessage = translate('you_have_unsaved_favorites');

	    __notif.addNotification(translatedMessage, 'favs');
	}
    }
})();
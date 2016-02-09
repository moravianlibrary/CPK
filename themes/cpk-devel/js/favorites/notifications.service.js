/**
 * 
 */
(function() {
    angular.module('favorites').factory('notifications', notificationsService);

    notificationsService.$inject = [ 'translateFilter' ];

    function notificationsService(translate) {

	var notifications = {
	    favAdded : favAdded,
	    allFavsRemoved : allFavsRemoved
	};

	var addedSomethingAlready = false;

	return notifications;

	// Public methods

	function favAdded() {

	    if (addedSomethingAlready === false) {
		addedSomethingAlready = true;

		createNotificationWarning();
	    }
	}

	function allFavsRemoved() {

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

	// Private methods

	function createNotificationWarning() {

	    var translatedMessage = translate('you_have_unsaved_favorites');

	    __notif.addNotification(translatedMessage, 'favs');
	}
    }
})();
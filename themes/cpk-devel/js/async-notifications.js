//FIXME: Delete all the notifications when logging out !!

$(function() { // Onload DOM ..
    // Get the notifies object if any
    localforage.getItem('notifies', function(err, lastNotifies) {
	__notif.printErr(err, lastNotifies);

	if (!lastNotifies) {
	    __notif.fetchNotifications();
	} else {
	    __notif.processSavedNotifications(lastNotifies);
	}
    });
});

var __notif = {
    // Time to wait until next refresh of the notifications in milisecs
    toWait : 60 * 60 * 1000,
    // We want only to save the fetched items after all institutions are fetched
    itemsToFetch : 0, // it'll get incremented as we'll iterate over the
    // institutions
    responses : [],
    // Async notifications loader
    fetchNotifications : function() {
	$('[data-identity].notification').each(function() {
	    ++__notif.itemsToFetch;

	    var cat_username = $(this).attr('data-identity');

	    __notif.fetchNotificationsForCatUsername(cat_username);
	});
    },
    fetchNotificationsForCatUsername : function(cat_username) {

	$.ajax({
	    type : 'POST',
	    url : '/AJAX/JSON?method=fetchNotifications',
	    dataType : 'json',
	    async : true,
	    // json object to sent to the authentication url
	    data : {
		cat_username : cat_username
	    },
	    success : function(response) {
		__notif.updateNotifies(response);
		__notif.processNotificationsFetched(response);
	    },
	    error : function(err) {
		__notif.printErr(err);
	    }
	})
    },
    processSavedNotifications : function(lastNotifies) {

	// Decide whether will we renew the notifications
	if (lastNotifies.timeSaved) {
	    var shouldWeFetchAgain = this.toWait + lastNotifies.timeSaved < Date
		    .now();

	    if (shouldWeFetchAgain)
		return this.fetchNotifications();
	} else {
	    this.fetchNotifications();
	}

	// Print saved values ..
	$.each(lastNotifies.responses, function(i, response) {
	    __notif.processNotificationsFetched(response);
	});
    },
    updateNotifies : function(response) {

	this.responses.push(response);

	this.saveResponses();
    },
    // This function will save all the responses fetched
    saveResponses : function() {

	function privateSavingFunc() {
	    // Call the async item getting

	    var lastNotifies = {};

	    lastNotifies.responses = __notif.responses;
	    lastNotifies.timeSaved = Date.now();

	    localforage.setItem('notifies', lastNotifies, function(err, val) {
		__notif.printErr(err, val);
	    })

	}

	// have we fetched all the institutions ?
	if (this.responses.length == this.itemsToFetch) {
	    privateSavingFunc();
	}
    },
    // This function will render the html passed to it ..
    processNotificationsFetched : function(response) {

	var data = response.data, status = response.status;

	var cat_username = data.cat_username, html = data.html, count = data.count;

	var jquerySelector = '[data-identity=' + cat_username
		+ '].notification';
	var div = $(jquerySelector);

	if (!div.length) {
	    console.error("jQuery selector '" + jquerySelector
		    + "' returned zero matches !");
	    return null;
	}

	if (status == 'OK') {
	    // Also change the icon so that it is more likely to be spotted
	    // (probably with count of notifications ?)
	    // Overwrite current div with the new one from renderer
	    div[0].outerHTML = html;

	    if (count > 0) {

		var counter = $('#notification-counter'), currentCount = parseInt(counter
			.text());
		currentCount += count;

		counter.text(currentCount);

		counter.show();
	    }

	} else { // We have recieved an error
	    div.children('i').remove();

	    var message = data.message;
	    if (typeof message == "undefined")
		message = "Unknown error occured";

	    div.children('span.label').text(message).removeClass(
		    'label-primary').addClass('label-danger');
	}
    },
    // Error printing function
    printErr : function(err, val) {
	if (err) {
	    console.error("localforage produced an error: " + err);
	    if (val) {
		console.error("value having problem with is '" + val + "'");
	    }
	}
    }

}

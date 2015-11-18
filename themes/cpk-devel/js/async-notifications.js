// TODO: Let the "MyResearch/Profile" fetch the notifications regardless they expired .. using it's getMyProfile method of course ..
$(function() { // Onload DOM ..
    // Get the notifies object if any
    localforage.getItem('notifies', function(err, lastNotifies) {
	__notif.printErr(err, lastNotifies);

	if (!lastNotifies) {
	    __notif.fetchNotifications();
	} else {
	    __notif.lastSaved = lastNotifies.timeSaved;
	    __notif.responses = lastNotifies.responses;
	    __notif.processSavedNotifications();
	}
    });
});

var __notif = {
    development : true,
    // Time to wait until next refresh of the notifications in milisecs
    toWait : 60 * 60 * 1000,
    // We want only to save the fetched items after all institutions are fetched
    itemsToFetch : 0, // it'll get incremented as we'll iterate institutions
    responses : {},
    lastSaved : 0,
    // Async notifications loader
    fetchNotifications : function() {
	$('[data-identity].notification').each(function() {
	    ++__notif.itemsToFetch;

	    var cat_username = $(this).attr('data-identity');

	    __notif.fetchNotificationsForCatUsername(cat_username);
	});
    },
    // Create a query to fetch notifications about one institution
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
	});
    },
    // Recovers saved notifications from the local database
    processSavedNotifications : function() {

	// Decide whether will we renew the notifications
	if (this.lastSaved) {
	    var shouldWeFetchAgain = this.toWait + this.lastSaved < Date
		    .now();

	    if (shouldWeFetchAgain)
		return this.fetchNotifications();
	} else {
	    return this.fetchNotifications();
	}
	
	// Check for another identities / delete disconnected ones
	this.syncIdentities();

	// Print saved values ..
	$.each(this.responses, function(i, response) {
	    __notif.processNotificationsFetched(response);
	});
    },
    // Updates saved notifications
    updateNotifies : function(response) {

	var cat_username = response.data.cat_username;

	this.responses[cat_username] = response;

	// have we fetched all the institutions ?
	if (Object.keys(this.responses).length == this.itemsToFetch) {

	    // This one is called only after fresh notifications were fetched
	    this.saveLastNotifies(); 
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
    // Check for (dis)connected identities
    syncIdentities : function() {
	// Keys of responses are actually cat_usernames
	var tmpIdentities = Object.keys(this.responses);
	
	$('[data-identity].notification').each(function() {
	    var cat_username = $(this).attr('data-identity');
	    
	    var i = tmpIdentities.indexOf(cat_username.replace(/\./,"\\."));
	    
	    if (i > -1) {
		// We found this identity in the storage
		tmpIdentities.splice(i, 1);
	    } else {
		// New identity connected
		
		// Update the itemsToFetch int as we may need it on "invoked refresh"
		++__notif.itemsToFetch;
		
		// Fetch notificatios for new cat_username
		__notif.fetchNotificationsForCatUsername(cat_username);
	    }
	});
	
	if (tmpIdentities.length > 0) {
	    // Some identities were disconnected
	    
	    // Update the itemsToFetch int as we may need it on "invoked refresh"
	    __notif.itemsToFetch -= tmpIdentities.length;
	    
	    // Remove those disconnected identites from storage
	    __notif.clearIdentities(tmpIdentities);
	}
    },
    // Clears provided identity's stored notification
    // Useful e.g. while disconnecting an account ..
    clearIdentities : function(cat_usernames) {
	var responsesTmp = {};
	
	Object.keys(this.responses).forEach(function(key) {
	    if (cat_usernames.indexOf(key) == -1)
		responsesTmp[key] = __notif.responses[key];
	});
	
	this.responses = responsesTmp;
	
	// This one is called only if we have young enough notifications
	this.saveLastNotifies();
	
    },
    // Do not call this function twice - as it'd result in an error
    saveLastNotifies : function() {
	var lastNotifies = {
		responses: this.responses,
		timeSaved: Date.now()
	};
	
	localforage.setItem('notifies', lastNotifies, function(err, val) {
	    __notif.printErr(err, val);
	});
    },
    // Error printing function
    printErr : function(err, val) {
	if (this.development && err) {
	    console.error("localforage produced an error: " + err);
	    if (val) {
		console.error("value having problem with is '" + val + "'");
	    }
	}
    }

}

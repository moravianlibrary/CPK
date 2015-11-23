// FIXME: Reduce selectors as much as possible as it'll speed up loading a lot - on mobile devices especially
$(function() { // Onload DOM ..

    // Initialize the notifications' pointers
    __notif.init();

    // We need to acknowledge user about global notifications
    __notif.global.fetch();

    // Now fetch all blocks from institutions user is in
    // __notif.blocks.fetch();

    // Now fetch all transactions from institutions user is in
    // __notif.transactions.fetch();

    // Dont' be passive unless on Profile page ..
    var shouldBePassive = document.location.pathname
	    .match(/^\/[a-zA-Z]+\/Profile$/);

    if (!shouldBePassive)

	// Get the notifies object if any
	localforage.getItem('notifies', function(err, lastNotifies) {
	    __notif.printErr(err, lastNotifies);

	    if (!lastNotifies) {
		__notif.blocks.fetchBlocks();
	    } else {
		__notif.blocks.lastSaved = lastNotifies.timeSaved;
		__notif.blocks.responses = lastNotifies.responses;
		__notif.blocks.processSavedBlocks();
	    }
	});
});

var __notif = {

    development : true,

    groupClass : 'identity-notifications',

    allowedClasses : [ 'default', 'info', 'warning', 'danger', 'success' ],

    addToCounter : function(count) {
	if (typeof count == "number" && count != 0) {

	    var currentCount = parseInt(__notif.pointers.counter.text());

	    currentCount += count;
	    __notif.pointers.counter.text(currentCount);

	    if (currentCount !== 0) {
		__notif.pointers.counter.show();
	    } else {
		__notif.pointers.counter.hide();
	    }

	}
    },

    // Error printing function
    printErr : function(err, val) {
	if (this.development && err) {
	    console.error("notifications.js produced an error: " + err);
	    if (val) {
		console.error("value having problem with is '" + val + "'");
	    }
	}
	return false;
    },
    getIdentityNotificationsElement : function(source) {
	if (typeof source == "undefined") {
	    // Set default identity
	    return __notif.pointers.global;
	}

	var identityNotificationsElement = __notif.pointers.institutions[source];

	// Did we find the identity?
	if (identityNotificationsElement === undefined
		|| !identityNotificationsElement.length) {

	    var message = "Pointer for '" + source
		    + "' wasn't properly initialized."
		    + " An attempt to resolve it failed";

	    return this.printErr(message);
	}

	return identityNotificationsElement;
    }
}

/**
 * Appends a notification message.
 * 
 * Syntax is: __notif.addNotification( message [, msgclass , institution ] )
 * 
 * msgclass can be one of __notif.allowedClasses
 * 
 * institution can be any string defining the source the MultiBackend uses to
 * recognize an institution
 * 
 */
__notif.addNotification = function(message, msgclass, institution,
	incrementCounter) {
    if (message === undefined) {
	return this.printErr("Please provide message to notify about.");
    }

    var identityNotificationsElement = this
	    .getIdentityNotificationsElement(institution);

    if (identityNotificationsElement == false)
	return false;

    // Create the notification Element
    var notif = document.createElement('div');

    if (msgclass === undefined || this.allowedClasses.indexOf(msgclass) == -1) {
	msgclass = 'default';
    }

    if (incrementCounter === undefined) {
	incrementCounter = true;
    }

    var clazz = 'notif-' + msgclass;

    if (!incrementCounter) {
	clazz += ' counter-ignore';
    } else {
	this.addToCounter(1);
    }

    notif.setAttribute('class', clazz);
    notif.textContent = message;

    // Append it
    identityNotificationsElement.append(notif);

    identityNotificationsElement.children('[data-type=loader]').remove();

    return true;
};

__notif.blocks = {
    // Time to wait until next refresh of the blocks in milisecs
    toWait : 60 * 60 * 1000,
    // We want only to save the fetched items after all institutions are
    // fetched
    institutionsToFetch : 0, // it'll get incremented as we'll iterate
    // institutions
    responses : {},
    lastSaved : 0,
    // Async notifications loader
    fetchBlocks : function() {

	Object.keys(__notif.pointers.institutions).forEach(function(source) {
	    var institution = __notif.pointers.institutions[source];

	    var cat_username = institution.attr('data-notif-identity');
	    __notif.blocks.fetchBlocksForCatUsername(cat_username);

	});
    },
    // Create a query to fetch notifications about one institution
    fetchBlocksForCatUsername : function(cat_username) {

	if (cat_username === undefined) {
	    return __notif
		    .printErr("Cannot fetch blocks for unknown username!");
	}

	++this.institutionsToFetch;

	$.ajax({
	    type : 'POST',
	    url : '/AJAX/JSON?method=fetchBlocks',
	    dataType : 'json',
	    async : true,
	    // json object to sent to the authentication url
	    data : {
		cat_username : cat_username
	    },
	    success : function(response) {
		__notif.blocks.updateNotifies(response);
		__notif.blocks.processNotificationsFetched(response);
	    },
	    error : function(err) {
		__notif.printErr(err);
	    }
	});
    },
    // Recovers saved notifications from the local database
    processSavedBlocks : function() {

	// Decide whether will we renew the notifications
	if (this.lastSaved) {
	    var shouldWeFetchAgain = this.toWait + this.lastSaved < Date.now();

	    if (shouldWeFetchAgain)
		return this.fetchBlocks();
	} else {
	    return this.fetchBlocks();
	}

	// Check for another identities / delete disconnected ones
	this.syncIdentities();

	// Print saved values ..
	$.each(this.responses, function(i, response) {
	    __notif.blocks.processNotificationsFetched(response);
	});
    },
    // Updates saved notifications
    updateNotifies : function(response) {

	var institution = response.data.source;

	this.responses[institution] = response;

	// have we fetched all the institutions ? 
	// FIXME possible unexpected behavior?
	if (Object.keys(this.responses).length >= this.institutionsToFetch) {

	    // This one is called only after fresh notifications were
	    // fetched
	    this.saveLastNotifies();
	}
    },
    // This function will render the html passed to it ..
    processNotificationsFetched : function(response) {

	var data = response.data, status = response.status;

	var institution = data.source, blocks = data.blocks, count = data.count, message = data.message;

	if (status == 'OK') {

	    if (count == 0)
		__notif.addNotification(message, 'info', institution, false);
	    else {
		Object.keys(blocks).forEach(
			function(key) {
			    __notif.addNotification(blocks[key], 'warning',
				    institution);
			});
	    }

	} else { // We have recieved an error
	    element.children('i').remove();

	    var message = data.message;
	    if (message === undefined)
		message = 'Unknown error occured';

	    element.children('span.label').text(message).removeClass(
		    'label-primary').addClass('label-danger');
	}
    },
    // Check for (dis)connected identities
    syncIdentities : function() {
	// Keys of responses are actually cat_usernames
	var tmpIdentities = Object.keys(this.responses);

	// Iterate over all institutions
	Object.keys(__notif.pointers.institutions).forEach(function(source) {

	    // Get the jQuery pointer to institution div
	    var institution = __notif.pointers.institutions[source];

	    // Did we have this identity already ?
	    var i = tmpIdentities.indexOf(source);

	    if (i > -1) {
		// Yes, this identity we know
		tmpIdentities.splice(i, 1);
	    } else {

		// No, we don't know anything about this identity ->
		// New identity connected

		// Fetch notificatios for new cat_username
		var cat_username = institution.attr('data-notif-identity');

		__notif.blocks.fetchBlocksForCatUsername(cat_username);

	    }
	});

	if (tmpIdentities.length > 0) {
	    // Some identities were disconnected

	    // Update the institutionsToFetch int as we may need it on
	    // "invoked
	    // refresh"
	    this.institutionsToFetch -= tmpIdentities.length;

	    // Remove those disconnected identites from storage
	    this.clearIdentities(tmpIdentities);
	}
    },
    // Clears provided identity's stored notification
    // Useful e.g. while disconnecting an account ..
    clearIdentities : function(institutions) {
	var responsesTmp = {};

	Object.keys(this.responses).forEach(function(key) {
	    if (institutions.indexOf(key) == -1)
		responsesTmp[key] = __notif.blocks.responses[key];
	});

	this.responses = responsesTmp;

	// This one is called only if we have young enough notifications
	this.saveLastNotifies();

    },
    // Do not call this function twice - as it'd probably result in an error
    saveLastNotifies : function() {
	var lastNotifies = {
	    responses : this.responses,
	    timeSaved : Date.now()
	};

	localforage.setItem('notifies', lastNotifies, function(err, val) {
	    __notif.printErr(err, val);
	});
    },

};

// Pointers point to various sections after init() is called
// Only parent pointer resolves whole notifications section
__notif.pointers = {
    parent : undefined,
    global : undefined, // Global notifications usually loaded synchronously
    counter : undefined, // Span holding the count of notifications

    institutions : {}
};

__notif.global = {
    // TODO: think about global notifications being parsed asynchronously ..
    fetch : function() {
	var initialCount = __notif.pointers.global
		.children('div:not(.counter-ignore)').length;

	__notif.addToCounter(initialCount);
    }
};

__notif.init = function() {
    // This function essentially stores all the pointers needed
    // to prevent doing multiple selects while they're slow

    var notifList = $('div#header-collapse nav ul li ul#notificationsList');

    __notif.pointers.parent = notifList;

    // Get all divs with any data-type
    var sections = notifList.children('li').children('div[data-type]');

    // Iterate over these & decide which one is global & which is an
    // institution div
    sections.each(function(i, section) {

	var type = section.getAttribute('data-type');

	if (type === 'global') {

	    __notif.pointers.global = $(section);
	} else if (type === 'institution') {

	    var source = section.getAttribute('data-source');

	    __notif.pointers.institutions[source] = $(section);
	}

    });

    if (__notif.pointers.global === undefined
	    || !__notif.pointers.global.length) {
	var message = 'Could not resolve notifications global pointer !\n'
		+ 'Please consider adding div[data-type=global] inside any <li>'
		+ "__notif.addNotification() won't work correctly until fixed";

	__notif.printErr(message);
    }

    // Resolve the counter span
    var counterSpan = notifList.siblings('a#notif_icon').children(
	    'span#notif-counter');

    if (counterSpan.length) {
	__notif.pointers.counter = counterSpan;
    } else {
	var message = 'Could not resolve counter span pointer !\n'
		+ "User won't see any number showed up when "
		+ 'notifications are added until fixed';

	__notif.printErr(message);
    }
};

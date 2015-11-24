$(function() { // Onload DOM ..

    // Initialize the notifications' pointers
    __notif.init();

    // We need to acknowledge user about global notifications
    __notif.global.fetch();

    // Now fetch all blocks from institutions user is in
    __notif.blocks.fetch();

    // Now fetch all transactions from institutions user is in
    __notif.fines.fetch();
});

var __notif = {

    options : {

	development : true,

	toWait : 60 * 60 * 1000, // Wait 60 minutes until next download

	allowedClasses : [ 'default', 'info', 'warning', 'danger', 'success' ],
    },

    addToCounter : function(count) {
	if (typeof count === 'number' && count !== 0) {

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
	if (this.options.development && err !== null) {
	    console.error("notifications.js produced an error: " + err);
	    if (val !== null) {
		console.error("value having problem with is '" + val + "'");
	    }
	}
	return false;
    },

    getIdentityNotificationsElement : function(source) {
	if (source === undefined) {
	    // Set default identity
	    return __notif.pointers.global;
	}

	var identityNotificationsElement = __notif.pointers.institutions[source];

	// Did we find the identity?
	if (identityNotificationsElement === undefined
		|| !identityNotificationsElement.length) {

	    var message = "Pointer for '" + source
		    + "' wasn't properly initialized."
		    + ' An attempt to resolve it failed';

	    this.printErr(message);

	    message = 'Are you sure you are trying to access existing institution notifications?';
	    return this.printErr(message);
	}

	return identityNotificationsElement;
    }
}

__notif.blocks = {
    // institutions
    responses : {},
    timeSaved : 0,

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = document.location.pathname
		.match(/^\/[a-zA-Z]+\/Profile/);

	if (!shouldBePassive)

	    // Get the notifies object if any
	    localforage.getItem('blocks', function(err, blocks) {

		__notif.printErr(err, blocks);

		// localforage returns null if not found
		if (blocks === null) {

		    __notif.blocks.downloadAll();
		} else {

		    __notif.blocks.responses = blocks.responses;
		    __notif.blocks.timeSaved = blocks.timeSaved;

		    __notif.blocks.processSaved();
		}
	    });
    },

    // Async notifications loader
    downloadAll : function() {
	Object.keys(__notif.pointers.institutions).forEach(function(source) {
	    var institution = __notif.pointers.institutions[source];

	    var cat_username = institution.attr('data-id');
	    __notif.blocks.downloadFor(cat_username);
	});
    },

    // Create a query to fetch notifications about one institution
    downloadFor : function(cat_username) {

	if (cat_username === undefined) {
	    return __notif.printErr('No cat_username provided !');
	}

	$.ajax({
	    type : 'POST',
	    url : '/AJAX/JSON?method=getMyBlocks',
	    dataType : 'json',
	    async : true,
	    data : {
		cat_username : cat_username
	    },
	    success : function(response) {
		__notif.blocks.saveResponse(response);
		__notif.blocks.processResponse(response);
	    },
	    error : function(err) {
		__notif.printErr(err);
	    }
	});
    },
    // Recovers saved notifications from the local database
    processSaved : function() {

	// Decide whether will we renew the notifications
	if (this.timeSaved) {
	    var shouldWeFetchAgain = __notif.options.toWait + this.timeSaved < Date
		    .now();

	    if (shouldWeFetchAgain)
		return this.downloadAll();
	} else {
	    return this.downloadAll();
	}

	// Check for another identities / delete disconnected ones
	this.syncInstitutions();

	// Print saved values ..
	$.each(this.responses, function(i, response) {
	    __notif.blocks.processResponse(response);
	});
    },
    // Updates saved notifications
    saveResponse : function(response) {

	var institution = response.data.source;

	this.responses[institution] = response;

	var institutionsCount = Object.keys(__notif.pointers.institutions).length;
	// have we fetched all the institutions ?
	// FIXME possible unexpected behavior?
	if (Object.keys(this.responses).length >= institutionsCount) {

	    // This one is called only after fresh notifications were
	    // fetched
	    this.save();
	}
    },
    // This function will render the blocks passed to it ...
    processResponse : function(response) {

	var data = response.data, status = response.status;

	var institution = data.source, blocks = data.blocks, count = data.count, message = data.message;

	if (status === 'OK') {

	    if (count === 0) {
		// Don't increment the counter as there is no block returned
		__notif.addNotification(message, 'info', institution, false);
	    } else {
		Object.keys(blocks).forEach(
			function(key) {
			    __notif.addNotification(blocks[key], 'warning',
				    institution);
			});
	    }

	} else { // We have recieved an error
	    // FIXME: Where is the element defined ??
	    element.children('i').remove();

	    var message = data.message;
	    if (message === undefined)
		message = 'Unknown error occured';

	    element.children('span.label').text(message).removeClass(
		    'label-primary').addClass('label-danger');
	}
    },
    // Check for (dis)connected identities
    syncInstitutions : function() {
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
		var cat_username = institution.attr('data-id');

		__notif.blocks.downloadFor(cat_username);

	    }
	});

	if (tmpIdentities.length > 0) {
	    // Some identities were disconnected

	    // Remove those disconnected identites from storage
	    this.clearInstitutions(tmpIdentities);
	}
    },
    // Clears provided identity's stored notification
    // Useful e.g. while disconnecting an account ..
    clearInstitutions : function(institutions) {
	var responsesTmp = {};

	Object.keys(this.responses).forEach(function(key) {
	    if (institutions.indexOf(key) === -1)
		responsesTmp[key] = __notif.blocks.responses[key];
	});

	this.responses = responsesTmp;

	// This one is called only if we have young enough notifications
	this.save();

    },
    // Do not call this function twice - as it'd probably result in an error
    save : function() {
	var blocks = {
	    responses : this.responses,
	    timeSaved : Date.now()
	};

	localforage.setItem('blocks', blocks, function(err, val) {
	    __notif.printErr(err, val);
	});
    },

};

__notif.fines = {
    // institutions
    responses : {},
    timeSaved : 0,

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = document.location.pathname
		.match(/^\/[a-zA-Z]+\/Fines/);

	if (!shouldBePassive)

	    // Get the notifies object if any
	    localforage.getItem('fines', function(err, fines) {
		__notif.printErr(err, fines);

		// localforage returns null if not found
		if (fines === null) {

		    __notif.fines.downloadAll();
		} else {

		    __notif.fines.responses = fines.responses;
		    __notif.fines.timeSaved = fines.timeSaved;

		    __notif.fines.processSaved();
		}
	    });
    },

    // Async notifications loader
    downloadAll : function() {
	Object.keys(__notif.pointers.institutions).forEach(function(source) {
	    var institution = __notif.pointers.institutions[source];

	    var cat_username = institution.attr('data-id');
	    __notif.fines.downloadFor(cat_username);
	});
    },

    // Create a query to fetch notifications about one institution
    downloadFor : function(cat_username) {

	if (cat_username === undefined) {
	    return __notif.printErr('No cat_username provided !');
	}

	$.ajax({
	    type : 'POST',
	    url : '/AJAX/JSON?method=getMyFines',
	    dataType : 'json',
	    async : true,
	    data : {
		cat_username : cat_username
	    },
	    success : function(response) {
		__notif.fines.saveResponse(response);
		__notif.fines.processResponse(response);
	    },
	    error : function(err) {
		__notif.printErr(err);
	    }
	});
    },
    // Recovers saved notifications from the local database
    processSaved : function() {

	// Decide whether will we renew the notifications
	if (this.timeSaved) {
	    var shouldWeFetchAgain = __notif.options.toWait + this.timeSaved < Date
		    .now();

	    if (shouldWeFetchAgain)
		return this.downloadAll();
	} else {
	    return this.downloadAll();
	}

	// Check for another identities / delete disconnected ones
	this.syncInstitutions();

	// Print saved values ..
	$.each(this.responses, function(i, response) {
	    __notif.fines.processResponse(response);
	});
    },
    // Updates saved notifications
    saveResponse : function(response) {

	var institution = response.data.source;

	this.responses[institution] = response;

	var institutionsCount = Object.keys(__notif.pointers.institutions).length;
	// have we fetched all the institutions ?
	// FIXME possible unexpected behavior?
	if (Object.keys(this.responses).length >= institutionsCount) {

	    // This one is called only after fresh notifications were
	    // fetched
	    this.saveLastNotifies();
	}
    },
    // This function will render the fines passed to it ...
    processResponse : function(response) {
	// TODO
	return false;
    },
    // Check for (dis)connected identities
    syncInstitutions : function() {
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
		var cat_username = institution.attr('data-id');

		__notif.fines.downloadFor(cat_username);

	    }
	});

	if (tmpIdentities.length > 0) {
	    // Some identities were disconnected

	    // Remove those disconnected identites from storage
	    this.clearInstitutions(tmpIdentities);
	}
    },
    // Clears provided identity's stored notification
    // Useful e.g. while disconnecting an account ..
    clearInstitutions : function(institutions) {
	var responsesTmp = {};

	Object.keys(this.responses).forEach(function(key) {
	    if (institutions.indexOf(key) === -1)
		responsesTmp[key] = __notif.fines.responses[key];
	});

	this.responses = responsesTmp;

	// This one is called only if we have young enough notifications
	this.save();
    },
    // Do not call this function twice - as it'd probably result in an error
    save : function() {
	var fines = {
	    responses : this.responses,
	    timeSaved : Date.now()
	};

	localforage.setItem('fines', fines, function(err, val) {
	    __notif.printErr(err, val);
	});
    },

};

__notif.global = {
    // TODO: think about global notifications being parsed asynchronously ..
    fetch : function() {
	var initialCount = __notif.pointers.global
		.children('div:not(.counter-ignore)').length;

	__notif.addToCounter(initialCount);
    }
};

// Pointers point to various sections after init() is called
// Only parent pointer resolves whole notifications section
__notif.pointers = {
    parent : undefined,
    global : undefined, // Global notifications usually loaded synchronously
    counter : undefined, // Span holding the count of notifications

    institutions : {}
};

/**
 * Appends a notification message.
 * 
 * Syntax is: __notif.addNotification( message [, msgclass , institution ] )
 * 
 * msgclass can be one of __notif.options.allowedClasses
 * 
 * institution can be any string defining the source the MultiBackend uses to
 * recognize an institution
 * 
 */
__notif.addNotification = function(message, msgclass, institution,
	incrementCounter) {
    if (message === undefined) {
	return this.printErr('Please provide message to notify about.');
    }

    var identityNotificationsElement = this
	    .getIdentityNotificationsElement(institution);

    if (identityNotificationsElement === false)
	return false;

    // Get the loading div if any
    var loader = identityNotificationsElement.children('[data-type=loader]');

    // Remove it if not already done
    if (loader.length)
	loader.remove();

    // Create the notification Element
    var notif = document.createElement('div');

    if (msgclass === undefined
	    || this.options.allowedClasses.indexOf(msgclass) === -1) {
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

    // Append the notification
    identityNotificationsElement.append(notif);

    return true;
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
	} else {
	    var msg = 'Unknown data-type encoutered within notifications';
	    __notif.printErr(msg);
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
$(function() { // Onload DOM ..

    // Initialize the notifications' pointers
    __notif.helper.init();

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

	    var currentCount = parseInt(__notif.helper.pointers.counter.text());

	    currentCount += count;
	    __notif.helper.pointers.counter.text(currentCount);

	    if (currentCount !== 0) {
		__notif.helper.pointers.counter.show();
	    } else {
		__notif.helper.pointers.counter.hide();
	    }

	}
    },
}

__notif.blocks = {
    // institutions
    responses : {},
    timeSaved : 0,
    ajaxMethod : 'getMyBlocks',
    localforageItemName : 'blocks',

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = document.location.pathname
		.match(/^\/[a-zA-Z]+\/Profile/);

	if (!shouldBePassive)
	    __notif.helper.fetch(this);
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
};

__notif.fines = {
    // institutions
    responses : {},
    timeSaved : 0,
    ajaxMethod : 'getMyFines',
    localforageItemName : 'fines',

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = document.location.pathname
		.match(/^\/[a-zA-Z]+\/Fines/);

	if (!shouldBePassive)
	    __notif.helper.fetch(this);
    },

    // This function will render the fines passed to it ...
    processResponse : function(response) {
	// TODO
	return false;
    },
};

__notif.global = {
    // TODO: think about global notifications being parsed asynchronously ..
    fetch : function() {
	var initialCount = __notif.helper.pointers.global
		.children('div:not(.counter-ignore)').length;

	__notif.addToCounter(initialCount);
    }
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

    var identityNotificationsElement = __notif.helper
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

__notif.helper = {

    // Pointers point to various sections after init() is called
    // Only parent pointer resolves whole notifications section
    pointers : {
	parent : undefined,
	global : undefined, // Global notifications usually loaded synchronously
	counter : undefined, // Span holding the count of notifications

	institutions : {}
    },

    syncingInstitutionsAlready : false,

    // Clears provided identity's stored notification
    // Useful e.g. while disconnecting an account ..
    clearInstitutions : function(handler, institutions) {
	var responsesTmp = {};

	Object.keys(handler.responses).forEach(function(key) {
	    if (institutions.indexOf(key) === -1)
		responsesTmp[key] = handler.responses[key];
	});

	handler.responses = responsesTmp;

	// This one is called only if we have young enough notifications
	__notif.helper.save(handler);

    },

    // Downloads all possible notifications within a handler
    downloadAll : function(handler) {

	var downloadCallback = function(source) {
	    var institution = __notif.helper.pointers.institutions[source];

	    var cat_username = institution.attr('data-id');
	    __notif.helper.downloadFor(handler, cat_username);
	};

	Object.keys(__notif.helper.pointers.institutions).forEach(
		downloadCallback);
    },

    // Create a query to fetch notifications about one institution
    downloadFor : function(handler, cat_username) {

	if (cat_username === undefined) {
	    return __notif.helper.printErr('No cat_username provided !');
	}

	$.ajax({
	    type : 'POST',
	    url : '/AJAX/JSON?method=' + handler.ajaxMethod,
	    dataType : 'json',
	    async : true,
	    data : {
		cat_username : cat_username
	    },
	    success : function(response) {

		__notif.helper.saveResponse(handler, response);

		handler.processResponse(response);
	    },
	    error : function(err) {
		__notif.helper.printErr(err);
	    }
	});
    },

    fetch : function(handler) {
	// Get the notifies object from storage
	localforage.getItem(handler.localforageItemName, function(err,
		savedResponses) {

	    __notif.helper.printErr(err, savedResponses);

	    __notif.helper.processSavedResponses(handler, savedResponses);
	});
    },

    getIdentityNotificationsElement : function(source) {
	if (source === undefined) {
	    // Set default identity
	    return __notif.helper.pointers.global;
	}

	var identityNotificationsElement = __notif.helper.pointers.institutions[source];

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
    },

    init : function() {
	// This function essentially stores all the pointers needed
	// to prevent doing multiple selects while they're slow

	var notifList = $('div#header-collapse nav ul li ul#notificationsList');

	__notif.helper.pointers.parent = notifList;

	// Get all divs with any data-type
	var sections = notifList.children('li').children('div[data-type]');

	// Iterate over these & decide which one is global & which is an
	// institution div
	sections.each(function(i, section) {

	    var type = section.getAttribute('data-type');

	    if (type === 'global') {

		__notif.helper.pointers.global = $(section);
	    } else if (type === 'institution') {

		var source = section.getAttribute('data-source');

		__notif.helper.pointers.institutions[source] = $(section);
	    } else {
		var msg = 'Unknown data-type encoutered within notifications';
		__notif.helper.printErr(msg);
	    }

	});

	if (__notif.helper.pointers.global === undefined
		|| !__notif.helper.pointers.global.length) {
	    var message = 'Could not resolve notifications global pointer !\n'
		    + 'Please consider adding div[data-type=global] inside any <li>'
		    + "__notif.addNotification() won't work correctly until fixed";

	    __notif.helper.printErr(message);
	}

	// Resolve the counter span
	var counterSpan = notifList.siblings('a#notif_icon').children(
		'span#notif-counter');

	if (counterSpan.length) {
	    __notif.helper.pointers.counter = counterSpan;
	} else {
	    var message = 'Could not resolve counter span pointer !\n'
		    + "User won't see any number showed up when "
		    + 'notifications are added until fixed';

	    __notif.helper.printErr(message);
	}
    },

    // Error printing function
    printErr : function(err, val) {
	if (__notif.options.development && err !== null) {
	    console.error("notifications.js produced an error: " + err);
	    if (val !== null) {
		console.error("value having problem with is '" + val + "'");
	    }
	}
	return false;
    },

    processSavedResponses : function(handler, savedResponses) {

	// localforage returns null if not found
	if (savedResponses === null) {

	    // Download then all
	    __notif.helper.downloadAll(handler);
	} else {
	    // Found some responses for handler provided

	    // Update handler's variables
	    handler.responses = savedResponses.responses;
	    handler.timeSaved = savedResponses.timeSaved;

	    // Decide whether will we renew the notifications
	    if (__notif.helper.shouldWeFetchAgain(handler)) {
		return __notif.helper.downloadAll(handler);
	    }

	    if (!__notif.helper.syncingInstitutionsAlready) {

		__notif.helper.syncingInstitutionsAlready = true;

		// Check for another identities / delete disconnected ones
		var currIdentities = Object.keys(savedResponses.responses);
		__notif.helper.syncInstitutions(handler, currIdentities);
	    }

	    // Print saved values ..
	    $.each(savedResponses.responses, function(i, response) {
		handler.processResponse(response);
	    });
	}
    },

    // Do not call this function twice - as it'd probably result in an error
    save : function(handler) {

	var localforageItem = {
	    responses : handler.responses,
	    timeSaved : Date.now()
	};

	localforage.setItem(handler.localforageItemName, localforageItem,
		function(err, val) {
		    __notif.helper.printErr(err, val);
		});
    },

    // Updates saved notifications
    saveResponse : function(handler, response) {

	var institution = response.data.source;

	handler.responses[institution] = response;

	var institutionsCount = Object
		.keys(__notif.helper.pointers.institutions).length;
	// have we fetched all the institutions ?
	// FIXME possible unexpected behavior?
	if (Object.keys(handler.responses).length >= institutionsCount) {

	    // This one is called only after fresh notifications were
	    // fetched
	    __notif.helper.save(handler);
	}
    },

    shouldWeFetchAgain : function(handler) {
	if (handler.timeSaved === undefined
		|| typeof handler.timeSaved !== 'number'
		|| handler.timeSaved !== 0) {

	    var shouldWeFetchAgain = __notif.options.toWait + handler.timeSaved < Date
		    .now();

	    return shouldWeFetchAgain;
	} else {
	    return true;
	}
    },

    // Check for (dis)connected identities
    syncInstitutions : function(handler, currIdentities) {

	var filterCallback = function(source) {

	    // Did we have this identity already ?
	    var i = currIdentities.indexOf(source);

	    if (i > -1) {
		// Yes, this identity we know
		currIdentities.splice(i, 1);
	    } else {

		// No, we don't know anything about this
		// identity ->
		// New identity connected

		// Fetch notificatios for new cat_username
		var cat_username = __notif.helper.pointers.institutions[source]
			.attr('data-id');

		__notif.helper.downloadFor(handler, cat_username);
	    }
	};

	// Perform the callback on all institutions
	Object.keys(__notif.helper.pointers.institutions).forEach(
		filterCallback);

	if (currIdentities.length > 0) {
	    // Some identities were disconnected

	    // Remove those disconnected identites from storage
	    __notif.helper.clearInstitutions(currIdentities);
	}
    },
};
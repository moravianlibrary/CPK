$(function() { // Onload DOM ..

    /*
     * Provide sync handlers before async as they may depend on each other
     */
    var handlers = [ __notif.global, __notif.blocks, __notif.fines ];

    // Initialize the notifications' pointers
    __notif.helper.init(handlers);
});

var __notif = {

    options : {

	development : true,

	version : '1.1.1',

	toWait : 60 * 60 * 1000, // Wait 60 minutes until next download

	allowedClasses : [ 'default', 'info', 'warning', 'danger', 'success' ],
    },
}

/**
 * Blocks handler
 */
__notif.blocks = {
    // institutions
    responses : {},
    timeSaved : 0,

    ajaxMethod : 'getMyBlocks',
    localforageItemName : 'blocks',

    isAsync : true,

    // Define eventListeners
    eventListeners : {
	click : function() {
	    __notif.warning.hide();
	    window.location = '/MyResearch/Profile';
	},
    },

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = $('body').children('div.template-name-profile').length !== 0;

	if (!shouldBePassive)
	    __notif.helper.fetch(__notif.blocks);
    },

    /**
     * This function will render the blocks passed to it ...
     * 
     * @param response
     * @return countOfNotificationsApplied *
     */
    processResponse : function(response) {

	var data = response.data, status = response.status;

	var institution = data.source, blocks = data.blocks, message = data.message;

	var count = 0;

	if (blocks instanceof Array) {
	    count = blocks.length;
	} else if (blocks instanceof Object) {
	    count = Object.keys(blocks).length;
	}

	if (status === 'OK') {

	    if (count !== 0) {
		Object.keys(blocks).forEach(
			function(key) {
			    __notif.addNotification(blocks[key], 'warning',
				    institution, true, __notif.blocks);
			});
	    }

	    return count;

	} else { // We have recieved an error
	    // TODO: Implement showing the error
	    count = 0;

	    return count;
	}
    },
};

__notif.fines = {
    // institutions
    responses : {},
    timeSaved : 0,

    ajaxMethod : 'getMyFines',
    localforageItemName : 'fines',

    isAsync : true,

    // Define eventListeners
    eventListeners : {
	click : function() {
	    window.location = '/MyResearch/Fines';
	},
    },

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = document.location.pathname
		.match(/^\/[a-zA-Z]+\/Fines/);

	if (!shouldBePassive)
	    __notif.helper.fetch(__notif.fines);
    },

    // This function will render the fines passed to it ...
    processResponse : function(response) {
	// TODO
	return 0;
    },
};

__notif.global = {

    hidden : false,

    withoutNotifications : false,

    isAsync : false,

    nothingRecievedCount : 0,

    informAboutNothingRecieved : function() {
	if (++__notif.global.nothingRecievedCount === __notif.helper.handlersCount) {

	    // Remove the loader
	    __notif.helper.pointers.global.siblings('div.notif-default')
		    .remove();

	    // Show the default message
	    __notif.helper.pointers.global.show();
	}
    },

    // TODO: think about global notifications being parsed asynchronously ..
    fetch : function() {
	var initialCount = __notif.helper.pointers.global
		.children('div:not(.counter-ignore)').length;

	if (initialCount > 0)
	    __notif.warning.show();
	else
	    __notif.global.informAboutNothingRecieved();

	// Is the message 'without_notifications' shown as the only one notif?
	__notif.global.withoutNotifications = __notif.helper.pointers.global
		.children('div:not(.without-notifs').length === 0;

    },

    notificationAdded : function() {
	// Hide the global section if we have added another notification
	// only if the global notification is about 'without_notifications'

	if (!__notif.global.hidden && __notif.global.withoutNotifications) {
	    __notif.global.hidden = true;

	    __notif.helper.pointers.global.parent('li').hide();
	}
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
	showWarningIcon, handler) {

    if (message === undefined) {
	return __notif.helper
		.printErr('Please provide message to notify about.');
    }

    // Create the notification Element
    var notif = document.createElement('div');

    // Set the default
    if (msgclass === undefined
	    || __notif.options.allowedClasses.indexOf(msgclass) === -1) {
	msgclass = 'default';
    }

    // Show warning Icon by default
    if (showWarningIcon === undefined) {
	showWarningIcon = true;
    }

    // This is notif-default by default
    var clazz = 'notif-' + msgclass;

    if (!showWarningIcon) {
	clazz += ' counter-ignore';
    } else {
	__notif.warning.show();
    }

    notif.setAttribute('class', clazz);
    notif.textContent = message;

    __notif.helper.appendNotification(notif, institution, handler);

    return true;
};

/**
 * Object holding two methods show() & hide() to show or hide notifications'
 * warning easily
 */
__notif.warning = {
    showedAlready : false,

    show : function() {
	if (!__notif.warning.showedAlready) {
	    __notif.warning.showedAlready = true;
	    __notif.helper.pointers.warningIcon.show();
	}
    },

    /**
     * Should we hide it permanently based on what user clicked?
     */
    hide : function() {
	if (__notif.warning.showedAlready) {
	    __notif.warning.showedAlready = false;
	    __notif.helper.pointers.warningIcon.hide();
	}
    }
};

__notif.helper = {

    // Defining helper ariables here

    /**
     * Array holding the handlers which has implemented method called
     * 'informAboutNothingRecieved'
     */
    handlersToInformAboutNothingRecieved : [],
    handlersToInformAboutNothingRecievedLength : 0,

    /**
     * Arrays each holding the sync/async handlers that were initialized
     */
    initializedAsyncHandlers : [],
    initializedSyncHandlers : [],

    initializedAsyncHandlersLength : 0,
    initializedSyncHandlersLength : 0,

    /**
     * Count of User's institutions (libraryCards within VuFind)
     */
    institutionsCount : 0,

    /**
     * Total count of all handlers initialized
     */
    handlersCount : 0,

    /**
     * Pointers point to various sections after init() is called Only parent
     * pointer resolves whole notifications section
     */
    pointers : {
	parent : undefined,
	global : undefined, // Global notifications usually loaded synchronously
	warningIcon : undefined, // Warning icon user should pay attention to

	institutions : {},
    },

    /**
     * Variable determining whether is syncing the institutions in process
     * alredy
     */
    syncingInstitutionsAlready : false,

    // Defining functions/methods here

    /**
     * Appends notification to an institution section & adds to it
     * eventListeners defined within a handler
     */
    appendNotification : function(notificationElement, institution, handler) {

	// Get the section of desired institution
	var identityNotificationsElement = __notif.helper
		.getIdentityNotificationsElement(institution);

	if (identityNotificationsElement === false)
	    return false;

	// Append eventListeners to the notification
	if (handler !== undefined && typeof handler.eventListeners === 'object'
		&& !(handler.eventListeners instanceof Array)) {

	    Object.keys(handler.eventListeners).forEach(function(key) {
		if (typeof handler.eventListeners[key] === 'function')
		    identityNotificationsElement.on(key, handler.eventListeners[key]);
	    });

	}
	// Append the notification
	identityNotificationsElement.append(notificationElement);

	// Unhide the section of desired institution if hidden
	identityNotificationsElement.parent('li').show();

	// Trigger the global's notificationAdded as it's interested into any
	// notifications being added
	__notif.global.notificationAdded();
    },

    /**
     * Check User's last version of this app used in order to delete
     * incompatible variables stored in localforage
     * 
     */
    checkVersion : function() {

	var localforageVersionName = '__notif.version';

	var setCurrVersion = function() {
	    // Set the version to the current version
	    localforage
		    .setItem(localforageVersionName, __notif.options.version);
	}

	var getVersionCallback = function(err, val) {
	    __notif.helper.printErr(err, val);

	    if (val !== __notif.options.version) {
		__notif.helper.clearTheCrumbs();

		// Set the version to the current version
		setCurrVersion();
	    }

	};

	localforage.getItem(localforageVersionName, getVersionCallback);
    },

    /**
     * Clears provided identity's stored notification Useful e.g. while
     * disconnecting an account ..
     * 
     * @param institutions
     */
    clearInstitutions : function(institutions) {

	for (var i = 0; i < __notif.helper.initializedAsyncHandlersLength; ++i) {

	    var responsesTmp = {};
	    var handler = __notif.helper.initializedAsyncHandlers[i];

	    Object.keys(handler.responses).forEach(function(key) {

		if (institutions.indexOf(key) === -1)
		    responsesTmp[key] = handler.responses[key];

	    });

	    handler.responses = responsesTmp;

	    // This one is called only if we have young enough notifications
	    __notif.helper.save(handler);
	}
    },

    /**
     * Clears all the notifications' object from the localforage storage :)
     */
    clearTheCrumbs : function() {

	localforage.iterate(function(value, key, iterationNumber) {

	    if (key.match(/^notif\./) !== null)
		localforage.removeItem(key);

	});
    },

    /**
     * Downloads all possible notifications within a handler
     * 
     * @param handler
     */
    download : function(handler) {

	var downloadCallback = function(source) {
	    var institution = __notif.helper.pointers.institutions[source];

	    var cat_username = institution.attr('data-id');
	    __notif.helper.downloadUsingCatUsername(handler, cat_username);
	};

	Object.keys(__notif.helper.pointers.institutions).forEach(
		downloadCallback);
    },

    /**
     * Downloads all possible notifications within all the initialized async
     * handlers
     * 
     * It basically calls __notif.helper.download(handler) for each one.
     */
    downloadForAllHandlers : function() {
	for (var i = 0; i < __notif.helper.initializedAsyncHandlersLength; ++i) {
	    var handler = __notif.helper.initializedAsyncHandlers[i];

	    __notif.helper.download(handler);
	}
    },

    /**
     * Downloads notifications for provided cat_username within all the
     * initialized async handlers
     * 
     * @param cat_username
     */
    downloadForAllHandlersUsingCatUsername : function(cat_username) {
	for (var i = 0; i < __notif.helper.initializedAsyncHandlersLength; ++i) {
	    var handler = __notif.helper.initializedAsyncHandlers[i];

	    __notif.helper.downloadUsingCatUsername(handler, cat_username);
	}
    },

    /**
     * Create a query to fetch notifications about one institution
     * 
     * @param handler
     * @param cat_username
     * @returns
     */
    downloadUsingCatUsername : function(handler, cat_username) {

	if (handler === undefined) {
	    return __notif.helper.printErr('No handler provided !');
	}

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

		__notif.helper.processResponse(handler, response);
	    },
	    error : function(err) {
		__notif.helper.printErr(err);
	    }
	});
    },

    /**
     * Perform the fetching for all identites using provided handler.
     * 
     * @param handler
     */
    fetch : function(handler) {

	if (handler.isAsync) {

	    // Get the notifies object from storage
	    localforage.getItem('__notif.' + handler.localforageItemName,
		    function(err, savedResponses) {

			__notif.helper.printErr(err, savedResponses);

			__notif.helper.processSavedResponses(handler,
				savedResponses);
		    });
	} else {
	    /*
	     * The handler does not care about storing any information as it is
	     * sync .. So we don't need to store responses
	     */
	    handler.fetch();
	}
    },

    /**
     * Retrieves pointer to an element holding all the notifications used within
     * that institution.
     * 
     * @param source
     * @returns
     */
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

	    __notif.helper.printErr(message);

	    message = 'Are you sure you are trying to access existing institution notifications?';
	    return __notif.helper.printErr(message);
	}

	return identityNotificationsElement;
    },

    /**
     * Error printing function
     * 
     * @param err
     * @param val
     * @returns {Boolean}
     */
    printErr : function(err, val) {
	if (__notif.options.development && err !== undefined && err !== null) {
	    console.error("notifications.js produced an error: " + err);
	    if (val !== undefined && val != null) {
		console.error("value having problem with is '" + val.toSource()
			+ "'");
	    }
	}
	return false;
    },

    /**
     * Serves to send the response to handler's own processResponse() method
     * 
     * @param handler
     * @param response
     * @param saveIt
     */
    processResponse : function(handler, response, saveIt) {

	if (saveIt === undefined || saveIt) {
	    __notif.helper.saveResponse(handler, response);
	}

	// Let the handler handle the response itself
	var countOfNotificationsAdded = handler.processResponse(response);

	if (countOfNotificationsAdded === undefined) {
	    var msg = 'Every handler must return number of processed '
		    + '̈́notifications from processResponse method!';

	    __notif.helper.printErr(msg);
	    return false;
	}

	if (countOfNotificationsAdded === 0) {
	    for (var i = 0; i < __notif.helper.handlersToInformAboutNothingRecievedLength; ++i) {
		__notif.helper.handlersToInformAboutNothingRecieved[i]
			.informAboutNothingRecieved(handler);
	    }
	}

	return countOfNotificationsAdded;
    },

    /**
     * Manages saving the responses used with provided async handler & calls the
     * handler.processResponse() method in order to have customizable behavior
     * for each async handler
     * 
     * @param handler
     * @param savedResponses
     * @returns
     */
    processSavedResponses : function(handler, savedResponses) {

	// localforage returns null if not found
	if (savedResponses === null) {

	    // Download all then (probably never fetched anything before)
	    __notif.helper.download(handler);
	} else {
	    // Found some responses for handler provided

	    // Update handler's variables
	    handler.responses = savedResponses.responses;
	    handler.timeSaved = savedResponses.timeSaved;

	    // Decide whether will we renew the notifications
	    if (__notif.helper.shouldWeFetchAgain(handler)) {
		return __notif.helper.download(handler);
	    }

	    if (!__notif.helper.syncingInstitutionsAlready) {

		__notif.helper.syncingInstitutionsAlready = true;

		// Check for another identities / delete disconnected ones
		var currIdentities = Object.keys(savedResponses.responses);
		__notif.helper.syncInstitutions(currIdentities);
	    }

	    // Print saved values ..
	    $.each(savedResponses.responses, function(i, response) {
		__notif.helper.processResponse(handler, response, false);
	    });
	}
    },

    /**
     * Do not call this function twice - as it'd probably result in an error.
     * 
     * It handles saving async handler's responses using localforage library.
     * 
     * @param handler
     */
    save : function(handler) {

	var localforageItem = {
	    responses : handler.responses,
	    timeSaved : Date.now()
	};

	handler.timeSaved = localforageItem.timeSaved;

	localforage.setItem('__notif.' + handler.localforageItemName,
		localforageItem, function(err, val) {
		    __notif.helper.printErr(err, val);
		});
    },

    /**
     * Updates saved notifications used by the provided async handler
     * 
     * @param handler
     * @param response
     */
    saveResponse : function(handler, response) {

	var institution = response.data.source;

	handler.responses[institution] = response;

	var institutionsCount = Object
		.keys(__notif.helper.pointers.institutions).length;

	/*
	 * Have we fetched all the institutions within this async handler?
	 */
	if (Object.keys(handler.responses).length >= institutionsCount) {

	    /*
	     * This one is called only after fresh notifications were fetched
	     */
	    __notif.helper.save(handler);
	}
    },

    /**
     * Decies if it is the time now to fetch notifications again using provided
     * handler.
     * 
     * @param handler
     * @returns {Boolean}
     */
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

    /**
     * Checks for (dis)connected identities
     * 
     * @param currIdentities
     */
    syncInstitutions : function(currIdentities) {

	var filterCallback = function(source) {

	    // Did we have this identity already ?
	    var i = currIdentities.indexOf(source);

	    if (i > -1) {
		// Yes, this identity we know
		currIdentities.splice(i, 1);
	    } else {

		/*
		 * No, we don't know anything about this identity -> New
		 * identity connected
		 */

		// Fetch notificatios for new cat_username
		var cat_username = __notif.helper.pointers.institutions[source]
			.attr('data-id');

		/*
		 * Redownload new identity for all the initialized async
		 * handlers
		 */
		__notif.helper
			.downloadForAllHandlersUsingCatUsername(cat_username);
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

/**
 * This function essentially stores all the pointers needed to prevent doing
 * multiple selects while they're slow.
 * 
 * It also checks for last version of notifications implementations in order to
 * clear the localforage to prevent bugs caused by version incompatibility.
 */
__notif.helper.init = function(handlers) {

    var notifList = $('div#header-collapse nav ul li ul#notificationsList');

    __notif.helper.pointers.parent = notifList;

    // Get all divs with any data-type
    var sections = notifList.children('li').children('div[data-type]');

    /*
     * Iterate over these & decide which one is global & which is an institution
     * div
     */
    sections.each(function(i, section) {

	var type = section.getAttribute('data-type');

	if (type === 'global') {

	    __notif.helper.pointers.global = $(section);
	} else if (type === 'institution') {

	    ++__notif.helper.institutionsCount;

	    var source = section.getAttribute('data-source');

	    __notif.helper.pointers.institutions[source] = $(section);
	} else if (type !== 'loader') {
	    var msg = 'Unknown data-type encountered within notifications';
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

    // Resolve the warning icon
    var warningIcon = notifList.siblings('a#notif_icon').children(
	    'i#notif-warning');

    if (warningIcon.length) {
	__notif.helper.pointers.warningIcon = warningIcon;
    } else {
	var message = 'Could not resolve warning icon pointer !\n'
		+ "User won't see any warning showed up when "
		+ 'notifications are added until fixed';

	__notif.helper.printErr(message);
    }

    // Now check user already updated his storage data
    __notif.helper.checkVersion();

    // Finally, initialize each handler
    if (typeof handlers === 'object' && handlers instanceof Array) {
	// Prepare for effective array iteration over handlers
	__notif.helper.handlersCount = handlers.length;

	for (var i = 0; i < __notif.helper.handlersCount; ++i) {
	    var handler = handlers[i];

	    /*
	     * Initialize all the handlers into the
	     * __notif.helper.initializedAsyncHandlers to be capable of
	     * effective identities synchronization
	     */
	    if (handler.isAsync) {
		__notif.helper.initializedAsyncHandlers.push(handler);
	    } else {
		__notif.helper.initializedSyncHandlers.push(handler);
	    }

	    /*
	     * Process methods informAboutNothingRecieved of each handler that
	     * desires to be informed everytime some handler did not recieve
	     * anything notifyable
	     */
	    if (handler.hasOwnProperty('informAboutNothingRecieved')
		    && typeof handler.informAboutNothingRecieved === 'function') {
		__notif.helper.handlersToInformAboutNothingRecieved
			.push(handler);
	    }
	}

	/*
	 * Set the lengths of all the handlers we have somehow splitted into
	 * custom arrays for effective parsing
	 */
	__notif.helper.initializedAsyncHandlersLength = __notif.helper.initializedAsyncHandlers.length;
	__notif.helper.initializedSyncHandlersLength = __notif.helper.initializedSyncHandlers.length;
	__notif.helper.handlersToInformAboutNothingRecievedLength = __notif.helper.handlersToInformAboutNothingRecieved.length;

	// Now fetch all the handlers
	for (i = 0; i < __notif.helper.handlersCount; ++i) {
	    handlers[i].fetch();
	}

    } else {
	var message = 'No handlers were specified to fetch !'
		+ 'consider adding at least __notif.global handler'
		+ 'as it\'s already synchronously implemented within VuFind';

	__notif.helper.printErr(message);
    }
};
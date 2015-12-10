$(function() { // Onload DOM ..

    /*
     * Provide sync handlers before async as they may depend on each other
     */
    var handlers = [ __notif.global, __notif.blocks, __notif.fines,
	    __notif.overdues ];

    // Initialize the notifications' pointers
    __notif.helper.init(handlers);
});

var __notif = {

    options : {

	development : true,

	version : '1.3.1',

	toWait : 60 * 60 * 1000, // Wait 60 minutes until next download

	allowedClasses : [ 'default', 'info', 'warning', 'danger', 'success' ],
    },
}

/**
 * Global handler
 */
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
 * Blocks handler
 */
__notif.blocks = {

    ajaxMethod : 'getMyProfile',

    /**
     * It must be unique across all async handlers!
     */
    localforageItemName : 'blocks',

    isAsync : true,

    // Define eventListeners
    eventListeners : {
	click : function() {

	    var source = this.parentElement.getAttribute('data-source');

	    __notif.sourcesRead.markAsRead(source, __notif.blocks);

	    window.location = '/MyResearch/Profile#' + source;

	    // Purge the class unread just in case user was on this page
	    this.classList.remove('notif-unread');
	},
    },

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = $('body').children('div.template-name-profile').length !== 0;

	if (!shouldBePassive)
	    __notif.helper.fetch(__notif.blocks);
    },
};

/**
 * Fines handler
 */
__notif.fines = {

    ajaxMethod : 'getMyFines',

    /**
     * It must be unique across all async handlers!
     */
    localforageItemName : 'fines',

    isAsync : true,

    // Define eventListeners
    eventListeners : {
	click : function() {

	    var source = this.parentElement.getAttribute('data-source');

	    __notif.sourcesRead.markAsRead(source, __notif.fines);

	    window.location = '/MyResearch/Fines#' + source;

	    // Purge the class unread just in case user was on this page
	    this.classList.remove('notif-unread');
	},
    },

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = document.location.pathname
		.match(/^\/[a-zA-Z]+\/Fines/);

	if (!shouldBePassive)
	    __notif.helper.fetch(__notif.fines);
    },
};

/**
 * Overdues handler
 */
__notif.overdues = {

    ajaxMethod : 'haveAnyOverdue',

    /**
     * It must be unique across all async handlers!
     */
    localforageItemName : 'overdues',

    isAsync : true,

    // Define eventListeners
    eventListeners : {
	click : function() {

	    var source = this.parentElement.getAttribute('data-source');

	    __notif.sourcesRead.markAsRead(source, __notif.overdues);

	    window.location = '/MyResearch/CheckedOut#' + source;

	    // Purge the class unread just in case user was on this page
	    this.classList.remove('notif-unread');
	},
    },

    fetch : function() {
	// Dont' be passive unless on Profile page ..
	var shouldBePassive = document.location.pathname
		.match(/^\/[a-zA-Z]+\/CheckedOut/);

	if (!shouldBePassive)
	    __notif.helper.fetch(__notif.overdues);
    },
};

/**
 * This function will render the blocks passed to it ...
 * 
 * @param response
 * @return boolean
 */
__notif.blocks.processResponse = function(response) {

    var data = response.data, status = response.status, institution = data.source;

    var blocks = data.blocks, blocksCount = Object.keys(blocks).length;

    var hasBlocks = (blocksCount > 0) ? true : false;

    if (status === 'OK') {

	if (hasBlocks) {

	    __notif.addNotification(VuFind.translate('you_have_blocks'),
		    'warning', institution, true, __notif.blocks);
	}

    } else { // We have recieved an error

	__notif.helper.printErr("Status recieved is not 'OK' !", response);
    }

    return hasBlocks;
};

/**
 * This function will render the fines passed to it ...
 * 
 * @param response
 * @return boolean
 */
__notif.fines.processResponse = function(response) {

    var data = response.data, status = response.status, institution = data.source;

    var fines = data.fines, finesKeys = Object.keys(fines), finesKeysLength = finesKeys.length;

    var hasDebt = false, credit = 0;

    // Sum up all the fines to find out if user has debt or credit ..
    for (var i = 0; i < finesKeysLength; ++i) {

	var fine = fines[finesKeys[i]];

	if (typeof fine !== 'object') {
	    // this is not a fine -> continue (fine is always an object)
	    continue;
	}

	var amount = fine.amount;
	if (amount === undefined) {

	    hasDebt = true;
	    break;
	}

	credit += amount;
    }

    // If not decided yet, check the user's credit
    if (hasDebt === false) {
	hasDebt = (credit < 0) ? true : false;
    }

    if (status === 'OK') {

	if (hasDebt) {

	    __notif.addNotification(VuFind.translate('you_have_fines'),
		    'warning', institution, true, __notif.fines);
	}

    } else { // We have recieved an error

	__notif.helper.printErr("Status recieved is not 'OK' !", response);
    }

    return hasDebt;
};

/**
 * This function will render the overdues passed to it ...
 * 
 * @param response
 * @return boolean
 */
__notif.overdues.processResponse = function(response) {

    var data = response.data, status = response.status, institution = data.source;

    var hasOverdues = data.overdue;

    if (hasOverdues === undefined) {
	hasOverdues = false;
    }

    if (status === 'OK') {

	if (hasOverdues) {

	    __notif.addNotification(VuFind.translate('you_have_overdues'),
		    'warning', institution, true, __notif.overdues);
	}

    } else { // We have recieved an error

	__notif.helper.printErr("Status recieved is not 'OK' !", response);
    }

    return hasOverdues;
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
	__notif.sourcesRead.handleShowingWarningIcon(institution, handler,
		notif);
    }

    // The sourcesRead might have already added a class - but we can't be sure
    var precedingClass = notif.getAttribute('class');
    if (precedingClass !== null) {
	clazz += " " + precedingClass;
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

__notif.sourcesRead = {

    /**
     * This array should be filled with all the callbacks which should be called
     * after the full initialization is done.
     */
    callbacksAfterFullInitialization : [],

    /**
     * Holds Boolean if we have already fully initialized all the sourcesRead
     * array so that new notifications can have read / unread classes with
     * certainty.
     */
    fullyInitialized : false,

    /**
     * All the values that were already read by the User.
     */
    values : [],

    /**
     * Adds a function to be called after __notif.sourcesRead gets fully
     * initialized, thus after localforage returns any response to
     * __notif.sourcesRead.init()
     * 
     * @param callback
     */
    addCallbackAfterFullInitialization : function(callback) {

	if (callback instanceof Function) {
	    __notif.sourcesRead.callbacksAfterFullInitialization.push(callback);
	} else {

	    var msg = 'Please provide a callback (instanceof Function) to '
		    + '__notif.sourcesRead.'
		    + 'addCallbackAfterFullInitialization(callback)';

	    __notif.helper.printErr(msg, arguments);
	}
    },

    /**
     * Creates String containing the source & handler's name to identify their
     * combinations within the values array.
     * 
     * @param source
     * @param handler
     * @returns {String}
     */
    craftSourceReadValue : function(source, handler) {
	return source + '.' + handler.localforageItemName;
    },

    /**
     * Flags all notifications within any institution unread. (Not only in the
     * variables' world :)
     */
    flagAllUnread : function() {

	var institutionKeys = Object.keys(__notif.helper.pointers.institutions);

	for (var i = 0; i < __notif.helper.institutionsCount; ++i) {
	    var institutionKey = institutionKeys[i];
	    __notif.helper.pointers.institutions[institutionKey].children()
		    .addClass('notif-unread');
	}

	__notif.sourcesRead.values = [];

	__notif.warning.show();
    },

    /**
     * Returns index of provided source associated to a handler
     * 
     * @param source
     * @param handler
     * @returns {Number}
     */
    getSourceReadIndex : function(source, handler) {

	var handlerOk = __notif.helper.checkHandlerIsValid(handler);

	if (handlerOk === false)
	    return -1;

	var toSearchFor = __notif.sourcesRead.craftSourceReadValue(source,
		handler);

	return __notif.sourcesRead.values.indexOf(toSearchFor);
    },

    /**
     * Determines whether we should show warning icon or not based on it's
     * status read / unread.
     * 
     * It also appends CSS class notif-unread to the provided element if the
     * notifications really is unread.
     * 
     * @param source
     * @param handler
     */
    handleShowingWarningIcon : function(source, handler, element) {

	// Define what to do
	var closure = function() {

	    var isUnread = __notif.sourcesRead
		    .isMarkedAsUnread(source, handler);

	    if (isUnread) {
		// Show the warning icon
		__notif.warning.show();

		// Append the unread CSS class
		var currClass = element.getAttribute('class'), newClass = 'notif-unread';

		// Also add the class which was there before (we're appending,
		// not replacing)
		if (currClass !== null)
		    newClass += " " + currClass;

		element.setAttribute('class', newClass);
	    }
	}

	if (__notif.sourcesRead.fullyInitialized) {

	    // We can do the logic immediately
	    closure.call();
	} else {

	    // Lets add the closure to the initialization queue
	    __notif.sourcesRead.addCallbackAfterFullInitialization(closure);
	}
    },

    /**
     * Initialize all sources which were read within any handler.
     * 
     * It basically reads sourcesRead from local storage unless those are
     * provided directly into this init function.
     */
    init : function(sourcesRead) {

	/*
	 * Define what to do after initialization is done first so that we can
	 * call it later
	 */
	var tasksAfterInitialization = function() {

	    __notif.sourcesRead.fullyInitialized = true;

	    /*
	     * Call all the callbacks needed to call after we have fetched the
	     * sourcesRead from localforage *
	     */
	    var initCallbacksLength = __notif.sourcesRead.callbacksAfterFullInitialization.length;

	    for (var i = 0; i < initCallbacksLength; ++i) {
		__notif.sourcesRead.callbacksAfterFullInitialization[i].call();
	    }
	}

	// Did we recieve the array defining sourcesRead ?
	if (sourcesRead !== undefined && sourcesRead instanceof Array) {

	    // SourcesRead were provided - probably just logged in ?
	    __notif.sourcesRead.values = sourcesRead;
	    __notif.sourcesRead.save();

	    return tasksAfterInitialization();
	}

	// No sourcesRead provided - ask local storage ..
	var getSourcesReadCallback = function(err, sourcesRead) {
	    __notif.helper.printErr(err, sourcesRead);

	    if (sourcesRead instanceof Array)
		__notif.sourcesRead.values = sourcesRead;

	    tasksAfterInitialization();
	};

	localforage.getItem('__notif.sourcesRead.values',
		getSourcesReadCallback);
    },

    /**
     * Checks if a source within handler is marked as read already.
     * 
     * @param source
     * @param handler
     * @returns {Boolean}
     */
    isMarkedAsRead : function(source, handler) {

	return __notif.sourcesRead.getSourceReadIndex(source, handler) !== -1;
    },

    /**
     * Checks if a source within handler is not marked as unread yet.
     * 
     * @param source
     * @param handler
     * @returns {Boolean}
     */
    isMarkedAsUnread : function(source, handler) {

	return __notif.sourcesRead.getSourceReadIndex(source, handler) === -1;
    },

    /**
     * Marks a source within handler as read already.
     * 
     * @param source
     * @param handler
     */
    markAsRead : function(source, handler) {

	if (__notif.sourcesRead.isMarkedAsUnread(source, handler)) {

	    var toPush = __notif.sourcesRead.craftSourceReadValue(source,
		    handler);

	    __notif.sourcesRead.values.push(toPush);
	    __notif.sourcesRead.save();

	    var callback = function(response) {
		console.log(response);
	    };

	    // Also let the server know about it ..
	    // Note that we NEED THIS TO BE SYNCHRONOUS
	    // in order to be sure we have let the server know before redirect
	    __notif.helper.doPOST(false, 'updateNotificationsRead', {
		curr_notifies_read : __notif.sourcesRead.values
	    }, callback, function(err) {
		/*
		 * We want to ignore errors as the AJAX call is probably going
		 * to be killed very soon (this AJAX call serves only to inform
		 * the server)
		 */
	    });
	}
    },

    /**
     * Marks a source within handler as unread yet.
     * 
     * TODO: When should we call this? Apparently after an notifications no
     * longer exists .. so that next one gets the user's focus
     * 
     * @param source
     * @param handler
     */
    markAsUnread : function(source, handler) {

	var handlerOk = __notif.helper.checkHandlerIsValid(handler);

	if (handlerOk === false)
	    return handlerOk;

	// Prepare for iteration over all the current values
	var sourcesReadLength = __notif.sourcesRead.values.length, newSourcesReadValues = [];

	var toSearchFor = __notif.sourcesRead.craftSourceReadValue(source,
		handler);

	// We need to purge any occurrence, so loop through it
	for (var i = 0; i < sourcesReadLength; ++i) {
	    var currVal = __notif.sourcesRead.values[i];

	    if (currVal !== toSearchFor)
		newSourcesReadValues.push(currVal);
	}

	// Replace current values with the new ones
	__notif.sourcesRead.values = newSourcesReadValues;
	__notif.sourcesRead.save();
    },

    /**
     * Source read saving function (saves the values using localforage)
     */
    save : function() {

	// Push it to local storage
	localforage.setItem('__notif.sourcesRead.values',
		__notif.sourcesRead.values, __notif.helper.printErrCallback);
    }
};

__notif.helper = {

    // Defining helper variables here

    /**
     * Array holding the handlers which has implemented method called
     * 'informAboutNothingRecieved'
     */
    handlersToInformAboutNothingRecieved : [],
    handlersToInformAboutNothingRecievedLength : 0,

    /**
     * Have we already callbacked the init function?
     */
    initCallbacked : false,

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
     * Object containing handlers associated with source which were read
     * already.
     */
    sourcesRead : [],

    /**
     * Variable determining whether is syncing the institutions in process
     * alredy
     */
    syncingInstitutionsAlready : false,

    // Defining functions/methods here

    addEasterEggs : function() {

	// Add "easter egg" bellClick
	var bellClickCallback = function() {
	    var clickount = this.getAttribute('data-clickount');
	    var clicktime = this.getAttribute('data-clicktime');

	    if (clickount === null || clicktime === null) {
		this.setAttribute('data-clickount', 1);
		this.setAttribute('data-clicktime', (new Date().getTime()));
	    } else {
		clickount = parseInt(clickount);
		clicktime = parseInt(clicktime);

		/*
		 * As you can see, the goal is to click at least 10 times the
		 * bell icon in 3 seconds.
		 */
		if ((new Date()).getTime() - 3e3 <= clicktime) {

		    (++clickount === 7) ? getTheEgg() :

		    this.setAttribute('data-clickount', clickount);

		} else {
		    this.setAttribute('data-clickount', 0);
		    this.setAttribute('data-clicktime', 0);
		}

	    }
	};

	// "Manually" flag all the notifications unread
	var getTheEgg = function() {
	    __notif.helper.clearTheCrumbs();

	    __notif.sourcesRead.flagAllUnread();

	    // Also let the server know about it ..
	    __notif.helper.doPOST(true, 'updateNotificationsRead', {
		curr_notifies_read : __notif.sourcesRead.values
	    }, function() {
	    });
	}

	__notif.helper.pointers.warningIcon.parent().on('click',
		bellClickCallback);
    },

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

	    Object.keys(handler.eventListeners).forEach(
		    function(key) {
			if (typeof handler.eventListeners[key] === 'function')
			    notificationElement.addEventListener(key,
				    handler.eventListeners[key]);
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
     * Checks if a handler provided has at least valid localforageItemName.
     * 
     * It else prints an console error message
     * 
     * @param handler
     * @returns {Boolean}
     */
    checkHandlerIsValid : function(handler) {
	if (handler === undefined || handler.localforageItemName === undefined) {
	    var msg = 'Did not provide valid handler !';

	    return __notif.helper.printErr(msg, handler);
	}

	return true;
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
     * 
     * It accepts a callback to call after the clearing is done
     * 
     * @param callback
     *                {Function}
     */
    clearTheCrumbs : function(callback) {

	var iterationCallback = function(value, key, iterationNumber) {

	    if (key.match(/^__notif\./) !== null)
		localforage.removeItem(key);
	};

	var iterationDoneCallback = function(err, result) {
	    __notif.helper.printErr(err, result);

	    if (callback instanceof Function) {
		callback.call();
	    }
	};

	// See http://mozilla.github.io/localForage/#iterate
	localforage.iterate(iterationCallback, iterationDoneCallback);
    },

    /**
     * Performs an asynchronous call to AJAX/JSON?method=ajaxMethod with
     * dataToSend provided.
     * 
     * Be sure to also provide function successCallback(response) to call after
     * successfully obtained the response
     * 
     * @param ajaxMethod
     * @param dataToSend
     * @param successCallback
     */
    doPOST : function(async, ajaxMethod, dataToSend, successCallback,
	    errCallback) {

	// Print all errors to console.error by default ..
	if (errCallback === undefined || typeof errCallback !== "function")
	    errCallback = function(err) {
		__notif.helper.printErr(err);
	    };

	$.ajax({
	    type : 'POST',
	    url : '/AJAX/JSON?method=' + ajaxMethod,
	    dataType : 'json',
	    async : async,
	    data : dataToSend,
	    success : successCallback,
	    error : errCallback
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

	var data = {
	    cat_username : cat_username
	};

	var successCallback = function(response) {

	    __notif.helper.processResponse(handler, response);
	};

	__notif.helper.doPOST(true, handler.ajaxMethod, data, successCallback);
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

	    if (typeof err === "object")
		err = err.toSource();

	    console.error("notifications.js produced an error: " + err);

	    if (val !== undefined && val != null) {
		console.error("value having problem with is '" + val.toSource()
			+ "'");
	    }
	}
	return false;
    },

    /**
     * Closure for default callback passed to localforage's methods only if we
     * are curious about errors
     * 
     * @param err
     * @param val
     */
    printErrCallback : function(err, val) {
	__notif.helper.printErr(err, val);
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
	var notificationAdded = handler.processResponse(response);

	if (notificationAdded === undefined
		|| typeof notificationAdded !== 'boolean') {

	    var msg = 'Every handler must return true/false if an notification was added!';

	    __notif.helper.printErr(msg);
	    return false;
	}

	if (notificationAdded === false) {
	    for (var i = 0; i < __notif.helper.handlersToInformAboutNothingRecievedLength; ++i) {
		__notif.helper.handlersToInformAboutNothingRecieved[i]
			.informAboutNothingRecieved(handler);
	    }
	}

	return notificationAdded;
    },

    /**
     * Calls the method processResponse asynchronously.
     * 
     * @param handler
     * @param response
     */
    processResponseAsynchronously : function(handler, response, saveIt) {
	setTimeout(function() {
	    __notif.helper.processResponse(handler, response, saveIt);
	}, 0);
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
     * Do not call this function twice for one handler - as it'd probably result
     * in an error.
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
		localforageItem, __notif.helper.printErrCallback);
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

    if (Cookies.getJSON('loggedOut') === 1
	    && __notif.helper.initCallbacked === false) {
	// We want this to be synchronous, so we'll call the init again after
	// it's done

	return __notif.helper.clearTheCrumbs(function() {
	    __notif.helper.initCallbacked = true;
	    __notif.helper.init(handlers);
	});
    }

    /*
     * Initialize all sourcesRead to be able to decide about "unread" flag with
     * all the new notifications ASAP
     */
    if (__notif.sourcesRead.fullyInitialized === false) {
	// There is a chance we have called it already (in case of logging in)
	__notif.sourcesRead.init();
    }

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
	    handlers[i].responses = {};
	    handlers[i].timeSaved = 0;
	    handlers[i].fetch();
	}

	__notif.helper.addEasterEggs();

    } else {
	var message = 'No handlers were specified to fetch !'
		+ 'consider adding at least __notif.global handler'
		+ 'as it\'s already synchronously implemented within VuFind';

	__notif.helper.printErr(message);
    }
};
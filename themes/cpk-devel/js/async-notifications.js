// TODO: Let the "MyResearch/Profile" fetch the notifications regardless they expired .. using it's getMyProfile method of course ..
// TODO: Prepare the notifications for being also on not logged in pages (in order to let user notify about anything ..)
$(function() { // Onload DOM ..
    // We need to actualize the counter from the synchronous global
    // notifications
    var mainId = $('#' + __notif.mainId);
    if (!mainId.length) {
	__notif.printErr("'#" + __notif.mainId + "' has no match");
    }

    var initialCount = mainId.children('div:not(.counter-ignore)').length;

    __notif.addToCounter(initialCount);

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

    mainId : 'notif-cpk',

    allowedClasses : [ 'default', 'info', 'warning', 'danger', 'success' ],

    getAllIdentityElements : function() {
	return $('[data-notif-identity].' + this.groupClass);
    },

    addToCounter : function(count) {
	if (typeof count == "number" && count != 0) {

	    var counter = $('#notif-counter'), currentCount = parseInt(counter
		    .text());

	    currentCount += count;
	    counter.text(currentCount);

	    // Show || hide :)
	    (currentCount != 0) ? counter.show() : counter.hide();

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
    getIdentityNotificationsElement : function(identifier) {
	if (typeof identifier == "undefined") {
	    // Set default identity
	    identifier = this.mainId;
	} else if (identifier.indexOf("notif-") != 0) {
	    // Prepend notif- to the identity source we probably recieved
	    // without "notif-"
	    identifier = 'notif-' + identifier;
	}

	var identityNotificationsElement = $('#' + identifier);

	// Did we find the identity?
	if (!identityNotificationsElement.length) {
	    return this.printErr("'#" + identifier + "' has no match");
	}

	return identityNotificationsElement;
    }
}

__notif.addNotification = function(message, msgclass, institution,
	incrementCounter) {
    if (typeof message == "undefined") {
	return this.printErr("Please provide message to notify about.");
    }

    var identityNotificationsElement = this
	    .getIdentityNotificationsElement(institution);

    if (identityNotificationsElement == false)
	return false;

    // Create the notification Element
    var notif = document.createElement('div');

    if (typeof msgclass == "undefined"
	    || this.allowedClasses.indexOf(msgclass) == -1) {
	msgclass = 'default';
    }

    if (typeof incrementCounter == "undefined") {
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

	__notif.getAllIdentityElements().each(function() {
	    var institution = $(this)[0].id;

	    // User mainId actually cannot have any blocks
	    // it serves for global notifications ..
	    if (institution != __notif.mainId) {
		var cat_username = $(this).attr('data-notif-identity');
		__notif.blocks.fetchBlocksForCatUsername(cat_username);
	    }
	});
    },
    // Create a query to fetch notifications about one institution
    fetchBlocksForCatUsername : function(cat_username) {

	if (typeof cat_username == "undefined") {
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
		Object.keys(blocks).forEach(function(key) {
		    __notif.addNotification(blocks[key], 'warning', institution);
		});
	    }

	} else { // We have recieved an error
	    element.children('i').remove();

	    var message = data.message;
	    if (typeof message == "undefined")
		message = "Unknown error occured";

	    element.children('span.label').text(message).removeClass(
		    'label-primary').addClass('label-danger');
	}
    },
    // Check for (dis)connected identities
    syncIdentities : function() {
	// Keys of responses are actually cat_usernames
	var tmpIdentities = Object.keys(this.responses);

	__notif.getAllIdentityElements().each(function() {
	    var institution = $(this)[0].id.replace(/^notif-/, '');

	    if (institution != __notif.mainId) {
		var i = tmpIdentities.indexOf(institution);

		if (i > -1) {
		    // We found this identity in the storage
		    tmpIdentities.splice(i, 1);
		} else {
		    // New identity connected

		    // Fetch notificatios for new cat_username
		    var cat_username = $(this).attr('data-notif-identity');

		    __notif.blocks.fetchBlocksForCatUsername(cat_username);

		}
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

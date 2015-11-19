// TODO: Let the "MyResearch/Profile" fetch the notifications regardless they expired .. using it's getMyProfile method of course ..
// TODO: Prepare the notifications for being also on not logged in pages (in order to let user notify about anything ..)
$(function() { // Onload DOM ..
    // We need to actualize the counter from the synchronous global
    // notifications
    var mainIdentity = __notif.getIdentityElement(__notif.mainIdentity);
    if (mainIdentity != null) {

	var initialCount = mainIdentity
		.children('ul.notification:not(.counter-ignore)').length;

	__notif.addToCounter(initialCount);
    }

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

    mainClass : 'identity-notifications',

    mainIdentity : 'cpk',

    getAllIdentityElements : function() {
	return $('[data-identity].' + this.mainClass);
    },

    addToCounter : function(count) {
	if (count != 0) {

	    var counter = $('#notification-counter'), currentCount = parseInt(counter
		    .text());
	    currentCount += count;

	    counter.text(currentCount);

	    counter.show();
	}
    },

    // Error printing function
    printErr : function(err, val) {
	if (this.development && err) {
	    console.error("localforage produced an error: " + err);
	    if (val) {
		console.error("value having problem with is '" + val + "'");
	    }
	}
    },
}

__notif.addNotification = function(data_identity, element) {
    if (typeof element == "object" && element instanceof Element) {

	var isInstitutionOnly;
	if (typeof data_identity != "undefined") {
	    isInstitutionOnly = data_identity.indexOf('.') == -1;

	    if (isInstitutionOnly) {
		// Make sure we have escaped the dot ..
		data_identity = data_identity.replace(/([^\\])\./, '$1\\.');
	    }

	} else {
	    console.error("data_identity cannot be null");
	    return false;
	}

	var ul = document.createElement('ul');

	ul.setAttribute('class', 'notification');
	ul.appendChild(element);

	var identifier;
	if (isInstitutionOnly) {
	    // We recieved only institution so we will pick institution
	    // notification area by regex, so match only beginning of the
	    // data-identity attr
	    identifier = '^' + data_identity;
	} else {
	    identifier = data_identity;
	}

	var identityNotifications = this.getIdentityElement(identifier,
		isInstitutionOnly);

	if (identityNotifications != null) {

	    identityNotifications.append(ul);

            this.addToCounter(1);
	} else {
	    return false;
	}
    } else {
	var message = "While using __notif.addNotification(data_identity, element) element must be instanceof Element !";
	console.error(message);
	return false;
    }

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
	    var cat_username = $(this).attr('data-identity');

	    // User mainIdentity actually cannot have any blocks
	    if (cat_username != __notif.mainIdentity) {
		++__notif.blocks.institutionsToFetch;

		__notif.blocks.fetchBlocksForCatUsername(cat_username);

	    }
	});
    },
    // Create a query to fetch notifications about one institution
    fetchBlocksForCatUsername : function(cat_username) {

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

	var cat_username = response.data.cat_username;

	this.responses[cat_username] = response;

	// have we fetched all the institutions ?
	if (Object.keys(this.responses).length == this.institutionsToFetch) {

	    // This one is called only after fresh notifications were
	    // fetched
	    this.saveLastNotifies();
	}
    },
    // This function will render the html passed to it ..
    processNotificationsFetched : function(response) {

	var data = response.data, status = response.status;

	var cat_username = data.cat_username, html = data.html, count = data.count;

	var element = __notif.getIdentityElement(cat_username);

	if (status == 'OK') {
	    // Overwrite current div with the new one from renderer
	    // FIXME do not remove the data-identity because of possible
	    // need of appending another notifications !
	    element[0].innerHTML = html;

	    __notif.addToCounter(count);

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

	__notif.getAllIdentityElements().each(
		function() {
		    var cat_username = $(this).attr('data-identity');

		    if (cat_username != __notif.mainIdentity) {
			var i = tmpIdentities.indexOf(cat_username.replace(
				/\./, "\\."));

			if (i > -1) {
			    // We found this identity in the storage
			    tmpIdentities.splice(i, 1);
			} else {
			    // New identity connected

			    // Update the institutionsToFetch int as we may
			    // need it on "invoked refresh"
			    ++__notif.blocks.institutionsToFetch;

			    // Fetch notificatios for new cat_username
			    __notif.blocks
				    .fetchBlocksForCatUsername(cat_username);
			}
		    }
		});

	if (tmpIdentities.length > 0) {
	    // Some identities were disconnected

	    // Update the institutionsToFetch int as we may need it on
	    // "invoked
	    // refresh"
	    __notif.blocks.institutionsToFetch -= tmpIdentities.length;

	    // Remove those disconnected identites from storage
	    __notif.blocks.clearIdentities(tmpIdentities);
	}
    },
    // Clears provided identity's stored notification
    // Useful e.g. while disconnecting an account ..
    clearIdentities : function(cat_usernames) {
	var responsesTmp = {};

	Object.keys(this.responses).forEach(function(key) {
	    if (cat_usernames.indexOf(key) == -1)
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

__notif.getIdentityElement = function(data_identity, isRegex) {

    if (typeof isRegex == "undefined")
	isRegex = false;

    var matches = null;

    if (!isRegex) {

	var jquerySelector = '[data-identity=' + data_identity + '].'
		+ this.mainClass;

	matches = $(jquerySelector);

	if (!matches.length) {
	    console.error("jQuery selector '" + jquerySelector
		    + "' returned zero matches !");
	    return null;
	}

    } else {
	// Query all the [data-identity="REGEX_HERE"]

	var jquerySelector = '[data-identity].' + this.mainClass;

	matches = $(jquerySelector).filter(
		function() {
		    return this.getAttribute('data-identity').match(
			    new RegExp(data_identity));
		});

	if (!matches.length) {
	    console.error("jQuery selector '" + jquerySelector
		    + "' filtered with regex '" + data_identity
		    + "' returned zero matches !");
	    return null;
	}
    }
    return matches;
};

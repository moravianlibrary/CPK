$(function() { // Onload DOM ..
    // Get the notifies object if any
    localforage.getItem('notifies', function(err, lastNotifies) {
	printErr(err);

	if (!lastNotifies) {
	    $('li[data-identity].notification').each(function() {
		var cat_username = $(this).attr('data-identity');

		fetchNotifications(cat_username);
	    });
	} else {
	    processSavedNotifications(lastNotifies);
	}
    });
});

// Async notifications loader
function fetchNotifications(cat_username) {

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
	    updateNotifies(response);
	    processNotificationsFetched(response);
	},
	error : function(err) {
	    printErr(err);
	}
    })
}

function processNotificationsFetched(response) {

    var data = response.data, status = response.status;

    var cat_username = data.cat_username, html = data.html, count = data.count;

    var jquerySelector = '[data-identity=' + cat_username + '].notification';
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

	div.children('span.label').text(message).removeClass('label-primary')
		.addClass('label-danger');
    }
}

function processSavedNotifications(lastNotifies) {
    $.each(lastNotifies, function(cat_username, response) {
	processNotificationsFetched(response);
    });
}

function updateNotifies(response) {

    // Call the async item getting
    localforage.getItem('notifies', function(err, lastNotifies) {
	printErr(err);

	if (!lastNotifies) {
	    lastNotifies = {};
	}

	var identity = response.data.cat_username;

	lastNotifies[identity] = response;

	localforage.setItem('notifies', lastNotifies, function(err) {
	    printErr(err);
	})
    });
}

function printErr(err) {
    if (err) {
	console.error("localforge produced an error: " + err);
    }
}
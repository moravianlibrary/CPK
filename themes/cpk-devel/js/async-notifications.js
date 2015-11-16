$(function() { // Onload DOM ..
    var lastNotifies = readCookie('notifies', true);
    if (typeof lastNotifies == "undefined" || ! lastNotifies) {
	$('li[data-identity].notification').each(function() {
	    var cat_username = $(this).attr('data-identity');

	    fetchNotifications(cat_username);
	});
    } else {
	processNotificationsFromCookie(lastNotifies);
    }
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
	    updateCookie(response);
	    processNotificationsFetched(response);
	},
	error : function(msg) {
	    // TODO: Think about showing the error somewhere somehow..
	    // alert(msg.toSource());
	}
    })
}

function processNotificationsFetched(response) {

    var data = response.data, status = response.status;

    var cat_username = data.cat_username, html = data.html, count = data.count;

    var div = $('li[data-identity=' + cat_username + '].notification');

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

function processNotificationsFromCookie(lastNotifies) {
    $.each(lastNotifies, function(cat_username, response) {
	processNotificationsFetched(response);
    });
}

function updateCookie(response) {
    var lastNotifies = readCookie('notifies', true);
    
    if (! lastNotifies) {
	lastNotifies = {};
    }

    var identity = response.data.cat_username;
    
    lastNotifies[identity] = response;
    
    // refer to eu-cookies.js
    createCookie('notifies', lastNotifies, 1, true); // Stay for one hour ..    
}
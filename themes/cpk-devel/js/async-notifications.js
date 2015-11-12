$(function() { // Onload DOM ..
    $('div[id].notification').each(function() {
	var cat_username = $(this).attr('id');

	fetchNotifications(cat_username);
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

    var cat_username = data.cat_username, html = data.html;

    var div = $('div#' + cat_username);

    if (status == 'OK') {
	// Also change the icon so that it is more likely to be spotted (probably with count of notifications ?)
	// Overwrite current div with the new one from renderer
	div[0].outerHTML = html;

    } else { // We have recieved an error
	div.children('i').remove();

	var message = data.message;
	if (typeof message == "undefined")
	    message = "Unknown error occured";

	div.children('span.label').text(message).removeClass('label-primary')
		.addClass('label-danger');
    }
}
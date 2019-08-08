$(function() { // Onload DOM ..
    $('table[id].table').each(function() {
	var cat_username = $(this).attr('id');

	fetchProfile(cat_username);
    })
    
    goToAnchorIfAny();
});

function goToAnchorIfAny() {
    var hasAnchor = window.location.href.match(/Profile[/]?#[a-z]+$/);
    if (hasAnchor !== null) {
	window.location = window.location.href;
    }
}

function fetchProfile(cat_username) {
    $.ajax({
	type : 'POST',
	url : '/AJAX/JSON?method=getMyProfile',
	dataType : 'json',
	async : true,
	// json object to sent to the authentication url
	data : {
	    cat_username : cat_username
	},
	success : function(response) {
	    updateProfileTable(response);
	    goToAnchorIfAny();
	}
    })
}

function updateProfileTable(response) {
    
    var patron = response.data, status = response.status;

    var cat_username = patron.cat_username, parentTable = {};

    if (cat_username) {
	parentTable = $('table[id="' + cat_username + '"].table');
    }

    if (parentTable.length) {
	if (status == "OK") {

	    // Remove the loading caption
	    parentTable.children('caption').remove();

	    // Update only recieved data
	    $.each(patron, function(key, val) {
		var tableRow = parentTable.find('tr[data-key=' + key + ']');

		if (tableRow.length) {
		    var rowCell = tableRow.children('td');

		    if (val && rowCell.length) {
			rowCell.html(val);
			tableRow.removeAttr('hidden');
		    }
		} else {
		    if (key == 'blocks' && typeof val == 'object') {

			$.each(val, function(logoUrl, blockMessage) {
			    
			    // Create division to put the logo & the message into
			    var errorMessage = $("<div>").addClass('alert alert-danger').text(blockMessage);
			    
			    parentTable.before(errorMessage);
			})
		    }
			if (key == 'message') {
				if (val!=null) {
					var errorMessage = $("<div>").addClass('alert alert-danger').text(val);

					parentTable.before(errorMessage);
				}
			}
			if (key == 'prolongRegistrationUrl') {
				if (val!=null) {
					var link = $("<a>").addClass('btn btn-default').attr('href', val).text(patron['prolongText']);

					parentTable.before(link);
				}
			}
		}
	    })

	} else {
	    var message = response.data.message, caption = parentTable
		    .children('caption');
	    
	    // Remove loading icon
	    caption.children('i').remove();

	    var label = caption.children('span.label');
	    
	    // Set red background to the label	    
	    label.removeClass('label-info').addClass('label-danger');
	    
	    // Print the message
	    if (message) {
		label.html(message);
	    } else {
		label.html('Unknown problem occured');
	    }
	    
	}
    } else {

	if (typeof response === "object" && typeof response.toSource !== "undefined") // Only Mozilla can convert object to source string ..
	    response = response.toSource();

	console.error("cat_username from the response was not found on this page .. cannot update the profile table! " + response, arguments);
    }
}
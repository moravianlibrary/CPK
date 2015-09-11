$(function() { // Onload DOM ..
    $('table[id].table.table-striped').each(function() {
	var cat_username = $(this).attr('id');

	fetchProfile(cat_username);
    })
});

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
	}
    })
}

function updateProfileTable(response) {
    var patron = response.data, status = response.status;

    if (status == "OK") {
	var cat_username = patron.cat_username, parentTable = {};

	if (cat_username) {
	    parentTable = $('table[id="' + cat_username
		    + '"].table.table-striped');
	}

	if (parentTable.length) {
	    
	    // Remove the loading caption
	    parentTable.children('caption').remove();
	    
	    // Update only recieved data
	    $.each(patron,
		    function(key, val) {
			var tableRow = parentTable.find('tr[data-key=' + key
				+ ']');
			
			if (tableRow.length) {
			    var rowCell = tableRow.children('td');

			    if (val && rowCell.length) {
				rowCell.html(val);
				tableRow.removeAttr('hidden');
			    }
			} else {
			    // TODO: if is key 'blocks', show warning message ..
			}
		    })
	}
    }
}
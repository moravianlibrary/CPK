$(function() { // Onload DOM ..
    $('div[data-type=loadingDiv]').each(function() {
	var cat_username = this.getAttribute('id');

	fetchTransactions(cat_username);
    })
    goToAnchorIfAny();
});

function goToAnchorIfAny() {
    var hasAnchor = window.location.href.match(/CheckedOut[/]?#[a-z]+$/);
    
    if (hasAnchor !== null) {
	window.location = window.location.href;
    }
}

function fetchTransactions(cat_username) {
    $.ajax({
	type : 'POST',
	url : '/AJAX/JSON?method=getMyTransactions',
	dataType : 'json',
	async : true,
	// json object to sent to the authentication url
	data : {
	    cat_username : cat_username
	},
	success : function(response) {
	    updateTransactions(response);
	    goToAnchorIfAny();
	}
    })
}

function updateTransactions(response) {

    // Update notifications not to let those fetch the blocks again ;)
    var nofifIsNotDefined = typeof __notif !== "undefined" && typeof __notif.overdues !== "undefined";
    if (! nofifIsNotDefined) {
	__notif.helper.processResponseAsynchronously(__notif.overdues, response);
    }
    
    var data = response.data, status = response.status;

    var cat_username = data.cat_username, html = data.html, overdue = data.overdue;
    // TODO process overdue somehow ..

    var pointer = $('div#' + cat_username);
    
    if (! pointer.length) {

	if (typeof response === "object" && typeof response.toSource !== "undefined") // Only Mozilla can convert object to source string ..
	    response = response.toSource();
	
	console.error("cat_username from the response was not found on this page .. cannot update checked out items! " + response, arguments);
	return;
    }
    
    // Overwrite current div with the new one from renderer
    
    if (status !== 'ERROR') 
	pointer[0].outerHTML = html;
    else {
	// FIXME ! rework to angular app with an html directive ..
	pointer[0].outerHTML = '<div class="row well" style="margin-bottom: 2px;">\
	    <div class="label label-danger">' + data.message + '</div>\
	  </div>';
    }
    
    // Decide if there will be cancel buttons or not ..    
    if (data.canRenew) {
	$('form#renewTransactions').removeAttr('hidden');
    }

    // Process obalkyKnih
    var obalkyLocal = data.obalky;

    if (typeof obalkyLocal !== 'undefined') {
	for ( var id in obalkyLocal) {
	    if (obalkyLocal.hasOwnProperty(id)) {
		var obalka = obalkyLocal[id];

		obalky.fetchImage(id, obalka.bibInfo, obalka.advert, 'icon');
	    }
	}
    }

}
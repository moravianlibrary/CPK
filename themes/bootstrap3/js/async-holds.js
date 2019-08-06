$(function() { // Onload DOM ..
    $('div[data-type=loadingDiv]').each(function() {
	var cat_username = this.getAttribute('id');

	fetchHolds(cat_username);
    })
    
    goToAnchorIfAny();
});

function goToAnchorIfAny() {
    var hasAnchor = window.location.href.match(/Holds[/]?#[a-z]+$/);
    if (hasAnchor !== null) {
	window.location = window.location.href;
    }
}

function fetchHolds(cat_username) {
    $.ajax({
	type : 'POST',
	url : '/AJAX/JSON?method=getMyHolds',
	dataType : 'json',
	async : true,
	// json object to sent to the authentication url
	data : {
	    cat_username : cat_username
	},
	success : function(response) {
	    updateHolds(response);
	    goToAnchorIfAny();
	}
    })
}

function updateHolds(response) {
    var data = response.data, status = response.status;

    var cat_username = data.cat_username, html = data.html;

    var pointer = $('div#' + cat_username);
    
    if (! pointer.length) {

	    if (typeof response === "object" && typeof response.toSource !== "undefined") // Only Mozilla can convert object to source string ..
		response = response.toSource();
	    
	    console.error("async-holdingsils.js produced an error while doing AJAX:\n" + response, arguments);
	console.error("cat_username from the response was not found on this page .. cannot update holds! " + response, arguments);
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
    if (data.canCancel) {
	$('form#cancelHold').removeAttr('hidden');
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
$(function() { // Onload DOM ..
    $('div[data-type=loadingDiv]').each(function() {
	var cat_username = $(this).attr('id');

	fetchHolds(cat_username);
    })
});

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
	}
    })
}

function updateHolds(response) {
    var data = response.data, status = response.status;

    var cat_username = data.cat_username, html = data.html;

    // Overwrite current div with the new one from renderer
    $('div#' + cat_username)[0].outerHTML = html;
    
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
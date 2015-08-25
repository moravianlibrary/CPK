function getBuyLinks( recordID, parentRecordID ) {
	var ajaxResponse = $.ajax({
		dataType: 'json',
		url: '/AJAX/JSON?method=getBuyLinks',
		data: { recordID: recordID, parentRecordID: parentRecordID },
		async: false,
		success: function( response ) {
			if( response.status !== 'OK' ) {
				// display the error message on each of the ajax status place holder
				$( "#ajax-error-info" ).empty().append( response.data );
			}
		}
	});
	return ajaxResponse.responseJSON;
}

function getSfxJibResult( sfxUrl, recordID, institute ) {
	var institute = typeof institute !== 'undefined' ? institute : 'ANY';
	var ajaxResponse = $.ajax({
		dataType: 'json',
		async: false,
		url: '/AJAX/JSON?method=callSfx',
		data: { recordID: recordID, institute: institute, sfxUrl: sfxUrl },
		success: function( response ) {
			if( response.status !== 'OK' ) {
				// display the error message on each of the ajax status place holder
				$( "#ajax-error-info" ).empty().append( response.data );
			}
		}
	});
	return ajaxResponse.responseJSON;
}

function showNextInstitutions(obj) {
    var anchors = obj.parentNode.parentNode.getElementsByTagName('a');
    
    $(anchors).each(function(key, val) {val.removeAttribute('hidden')});
    
    obj.remove();
}
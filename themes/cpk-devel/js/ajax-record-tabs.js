function getBuyLinks( recordID, parentRecordID, callback ) {
	$.ajax({
		dataType: 'json',
		url: '/AJAX/JSON?method=getBuyLinks',
		data: { recordID: recordID, parentRecordID: parentRecordID },
		async: true,
		success: function( response ) {
			if( response.status !== 'OK' ) {
				// display the error message on each of the ajax status place holder
				$( "#ajax-error-info" ).empty().append( response.data );
			} else {
				callback( response );
			}
		}
	});
}

function getSfxJibResult( sfxUrl, recordID, institute, arrayOf866 ) {
	var institute = typeof institute !== 'undefined' ? institute : 'ANY';

	$.ajax({
		dataType: 'json',
		async: true,
		url: '/AJAX/JSON?method=callSfx',
		data: { recordID: recordID, institute: institute, sfxUrl: sfxUrl },
		success: function( sfxJibResult ) {

			if( sfxJibResult.status !== 'OK' ) {

				// display the error message on each of the ajax status place holder
				$( "#ajax-error-info" ).empty().append( response.data );
			} else {

				if ( sfxJibResult.data[0].sfxResult.targets.target !== '' && arrayOf866 !== '') {
					var count = sfxJibResult.data[0].sfxResult.targets.target.length;

					for (var i = 0; i < count; i++) {
						var targetServiceId = sfxJibResult.data[0].sfxResult.targets.target[i].target_service_id;
						if ( arrayOf866[targetServiceId] != null ) {
							var record = arrayOf866[targetServiceId];
							var anchor = record.anchor;
							var source = record.source;
							var url = sfxJibResult.data[0].sfxResult.targets.target[i].target_url;
							$( "#e-version-table tbody" ).append( "<tr>"
									+"<td>"+databaseTranslation+"</td>"
									+"<td><span class='label label-warning'>"+unknownTranslation+"</span></td>"
									+"<td><a href='"+url+"'>"+anchor+"</a></td>"
									+"<td>"+source+"</td>"
									+"</tr>");

						}
					}
				}
			}
		}
	});
}

function get866( recordUniqueID, parentRecordID, callback ) {
	$.ajax({
		dataType: 'json',
		async: true,
		url: '/AJAX/JSON?method=get866',
		data: { parentRecordID: parentRecordID },
		success: function( response ) {
			if( response.status !== 'OK' ) {
				// display the error message on each of the ajax status place holder
				$( "#ajax-error-info" ).empty().append( response.data );
			} else {
				console.log( response );
				callback( recordUniqueID, response['data'][0]['field866'] );
			}
		}
	});
}


function display866( recordUniqueID, rawDataArrayOf866 ) {
	var arrayOf866 = {};
	
	var institute = 'MZK'; // @FIXME this is temporary hard-coded
	
	if (false != rawDataArrayOf866) {
		rawDataArrayOf866.forEach(function(entry) {
			var pole = entry.split("|");

			arrayOf866[pole[1]] = {
				'source': pole[0], 
				'anchor': pole[2]
			}
		});

		getSfxJibResult('http://sfx.jib.cz/sfxlcl3', recordUniqueID, institute, arrayOf866);

	}
}

function showNextInstitutions(obj) {
    var anchors = obj.parentNode.parentNode.getElementsByTagName('a');
    
    $(anchors).each(function(key, val) {val.removeAttribute('hidden')});
    
    obj.remove();
}
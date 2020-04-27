var holdingsILS = {

    bibId : undefined,

    pointers : {
	tbody : undefined
    },

    init : function() {

	holdingsILS.bibId = $('input.hiddenId').val();

	holdingsILS.pointers.tbody = $('body div#holdings-tab table tbody');

	holdingsILS.getHoldingStatuses();
    },

    // Async holdings loader
    getHoldingStatuses : function(ids, nit) {

		if (typeof ids !== 'object')
		    ids = holdingsILS.getHoldingsIds();
		
		var siglas = holdingsILS.getHoldingsSiglas( );
		
		if (typeof nit === 'undefined') { nit = null; }
	
		var activeFilter;
		// If we have active filter, append it to the query
		if (typeof holdingsILSfilters !== 'undefined') {
	
		    holdingsILSfilters.init();
	
		    activeFilter = holdingsILSfilters.activeFilter;
		}
	
		if (ids.length != 0) {

		    var data = {
			ids : ids,
			bibId : holdingsILS.bibId,
			next_item_token : nit,
			siglas : siglas
		    };
	
		    // Append the filter if any
		    if (typeof activeFilter !== 'undefined')
			data['activeFilter'] = activeFilter;
	
		    var source = data.bibId.split(".")[0];
		    
		    
		    if (source == 'caslin') {
	    	 $.ajax({
				type : 'POST',
				url : '/AJAX/JSON?method=getCaslinHoldingsStatuses',
				dataType : 'json',
				async : true,
				// json object to sent to the authentication url
				data : data,
				success : function(response) {
				    holdingsILS.processGetCaslinHoldingStatusesResponse(response);
				},
				error : function(msg) {
				    
				    if (typeof msg === "object" && typeof msg.toSource !== "undefined") // Only Mozilla can convert object to source string ..
					msg = msg.toSource();
				    
				    console.error("async-holdingsils.js produced an error while doing caslin AJAX:\n" + msg, arguments);
				}
			  });
		    } else {
		    	$.ajax({
					type : 'POST',
					url : '/AJAX/JSON?method=getHoldingsStatuses',
					dataType : 'json',
					async : true,
					// json object to sent to the authentication url
					data : data,
					success : function(response) {
					    holdingsILS.processGetHoldingStatusesResponse(response);
					},
					error : function(msg) {
					    
					    if (typeof msg === "object" && typeof msg.toSource !== "undefined") // Only Mozilla can convert object to source string ..
						msg = msg.toSource();
					    
					    console.error("async-holdingsils.js produced an error while doing AJAX:\n" + msg, arguments);
					}
				});
		    }
	    
		}
    },

    getHoldingsIds : function(includingBeingLoadedIds) {

	if (typeof includingBeingLoadedIds === 'undefined')
	    includingBeingLoadedIds = false;

	var ids = [];

	holdingsILS.getAllNotLoadedHoldings(includingBeingLoadedIds).each(function() {
	    ids.push($(this).attr('id').trim());

	    // Add loading class so that we know about being it parsed
	    $(this).addClass('loading');
	});

	return ids;
    },
    
    getHoldingsSiglas : function(includingBeingLoadedIds) {

    	if (typeof includingBeingLoadedIds === 'undefined')
    	    includingBeingLoadedIds = false;

    	var siglas = [];

    	holdingsILS.getAllNotLoadedHoldings(true).each(function() {
    		siglas.push($(this).attr('data-sigla'));

    	    // Add loading class so that we know about being it parsed
    	    $(this).addClass('loading');
    	});

    	return siglas;
    },

    getAllNotLoadedHoldings : function(includingBeingLoaded) {

	if (typeof includingBeingLoaded === 'undefined')
	    includingBeingLoaded = true;

	var selector;

	if (includingBeingLoaded) {
	    selector = 'tr:not(.loaded, .hidden)';
	} else {
	    selector = 'tr:not(.loading, .loaded, .hidden)';
	}

	return holdingsILS.pointers.tbody.children(selector);
    },

    processGetHoldingStatusesResponse : function(r) {

	var data = r.data;

	if (typeof data.statuses !== 'undefined') {

	    // Update the status
	    $.each(data.statuses, function(key, value) {
		holdingsILS.updateHoldingId(key, value);
	    });

	    if (data.remaining) {
		holdingsILS.getHoldingStatuses(data.remaining, data.next_item_token);
	    } else {
		holdingsILS.getAllNotLoadedHoldings(true).each(function() {
		    holdingsILS.updateHoldingId(this, data, true);
		});
	    }
	} else {

	    // Show error messages
	    holdingsILS.getAllNotLoadedHoldings(true).each(function() {
		holdingsILS.updateHoldingId(this, data, true);
	    });
	}
    },
    
    processGetCaslinHoldingStatusesResponse : function(r) {

    	var data = r.data;

    	if (typeof data.links !== 'undefined') {
    		
    		$( '#holdings-tab tr' ).each( function( index, element ) {
    			$.each( data.links, function( sigla, link ) {
    				if ( $( element ).attr( 'id' ) == sigla ) {
    					
    					var fullLink = link;
    					
    					if (fullLink.substring(0, 8) != "https://" && fullLink.substring(0, 7) != "http://") {
    						fullLink = 'http://'+fullLink;
    					}
    					
        				var htmlLink = '<a href="'+fullLink+'" target="_blank" title="'+VuFind.translate('caslin_tab_link')+'">'+VuFind.translate('caslin_tab_link')+'</a>';
        				$( element ).find( 'div[data-type="item-status"]' ).html( htmlLink );
        			}
    			});
    		});
    		
    		$( '#holdings-tab tr div[data-type="item-status' ).each( function( index, element ) {
    			if ( ! $( element ).find( 'a' ).length ) {
    				$( element ).html( '<i class="label label-info">'+VuFind.translate('unknown status')+'</label>' );
    			}
    		});
    	}
    	
    },

    updateHoldingId : function(id, value, setUnknownLabel) {

	if (typeof setUnknownLabel === 'undefined')
	    setUnknownLabel = false;

	var tableRow;

	if (!id)
	    return null;

	if (typeof id === 'object') {

	    tableRow = $(id);

	} else {
        var idSelector = id.replace( /(:|\.|\[|\]|,|=|@|#|\/)/g, "\\$1" );
        tableRow = holdingsILS.pointers.tbody.children("tr#" + idSelector);
        if (tableRow.length === 0) {
            tableRow = holdingsILS.pointers.tbody.children("tr[id^='" + idSelector + "']");
        }
	}

	var statusDiv = tableRow.find('td div[data-type=item-status]').first(), icon = statusDiv.children('i'), label = statusDiv.children('span.label');

	// Purge the loading icon
	icon.remove();

	var status = value.status;
	// Set status to the label
	if (typeof status !== 'undefined')
	    label.text(status);
	else {
	    label.text(VuFind.translate('unknown status'));
	    setUnknownLabel = true;
	}

	var availability = value.availability;

	if (typeof availability !== 'undefined') {
	    var availabilitySpan = statusDiv.parent().parent().find('td div[data-type=availability] span');
	    availabilitySpan.text(availability);
	}

	// divLink does not exist until logged in ..
	var divLink = tableRow.find('td div[data-type=link]').first();

	var dueDate = value.duedate, labelSet = false;
	if (typeof dueDate !== 'undefined' && dueDate) {

	    // We have some due date here ..
	    var dueDateColumn = tableRow.children('td[data-type=duedate]').first();
	    dueDateColumn.text(dueDate);

	    label.removeClass('label-info').addClass('label-warning');
	    labelSet = true;
	}

	if (value.addLink) {

	    toBeBroken: if (typeof divLink !== 'undefined') {
		// Show hidden link
		var linkSpan = divLink.find('a span'), holdType = value.holdtype;

		if (typeof holdType === 'undefined') {
		    holdType = 'Reserve';
		} else if (holdType === 'false') {
		    divLink.remove();
		    break toBeBroken;
		}

		linkSpan.text(VuFind.translate(holdType));

		divLink.removeAttr('hidden');
	    }
	} else {
	    divLink.remove();

	    if (setUnknownLabel) {
		label.removeClass('label-info').addClass('label-unknown');
		labelSet = true;
	    }
	}

	if (labelSet === false) {
	    var labelType = typeof value.label === 'undefined' ? 'label-success' : value.label;

	    label.removeClass('label-info').removeClass('label-unknown').addClass(labelType);
	}
	
	var availability = value.availability;
	if (typeof availability !== 'undefined' && availability) {
	    var availabilityColumn = tableRow.children('td[data-type=availability]').first();
	    availabilityColumn.text(availability);
	}
	
	var collection = value.collection;
	if (typeof collection !== 'undefined' && collection) {
	    var collectionColumn = tableRow.children('td[data-type=collection]').first();
	    collectionColumn.text(collection);
	}
	
	var department = value.department;
	if (typeof department !== 'undefined' && department) {
	    var departmentColumn = tableRow.children('td[data-type=department]').first();
	    departmentColumn.text(department);
	}
	
	var location = value.location;
    if (typeof location !== 'undefined' && location) {
        var locationColumn = tableRow.children('td[data-type=location]').first();
        locationColumn.text(location);
    }

	tableRow.removeClass('loading').addClass('loaded');

    },
}

// Init on DOM load
$(holdingsILS.init);

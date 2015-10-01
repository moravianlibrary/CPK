/**
 * This part is intended to maintain filters processing ...
 * 
 * To make this JS work, you have to include async-holdingsils.js too ...
 * 
 */
$(function() { // Append these eventListeners after DOM loaded ..
    $("#year_filter").on('change', function() {
	filterSelected('year', this.value);
    });

    $("#volume_filter").on('change', function() {
	filterSelected('volume', this.value);
    });

    $("#issue_filter").on('change', function() {
	filterSelected('issue', this.value);
    });
});

function filterSelected(filter, value) {

    var selector = 'tr[data-type=holding][hidden=hidden]';

    if (value == 'ALL') {

	var exclude = filter;
	var activeFilters = getSelectedOptions(exclude);

	for ( var activeFilter in activeFilters) {
	    var activeFilterValue = activeFilters[activeFilter];
	    if (activeFilterValue != 'ALL') {
		selector += '[data-' + activeFilter + '=' + activeFilterValue
			+ ']';
	    }
	}

    } else {

	// We have to consider currently selected filters ..
	var exclude = filter;
	var activeFilters = getSelectedOptions(exclude);

	var selectorToAppend = '';
	for ( var activeFilter in activeFilters) {
	    var activeFilterValue = activeFilters[activeFilter];
	    if (activeFilterValue != 'ALL') {
		selectorToAppend += '[data-' + activeFilter + '='
			+ activeFilterValue + ']';
	    }
	}

	// Hide unhidden rows which are about to be hidden
	$('tr[data-type=holding]:not([hidden=hidden]' + selectorToAppend + ')')
		.attr('hidden', 'hidden');

	selector += '[data-' + filter + '=' + value + ']';
	selector += selectorToAppend;
    }

    // Now unhide rows matching selected filters
    $(selector).removeAttr('hidden');

    // And now query the status of the unhidden
    getHoldingStatuses();
    
    // TODO: hide filter options which would result in an empty set
}

function getSelectedOptions(exclude) {

    var retVal = {};

    [ 'year', 'volume', 'issue' ].forEach(function(current) {
	if (exclude != current)
	    retVal[current] = $('#' + current + '_filter option:selected').val();
    });

    return retVal;
}
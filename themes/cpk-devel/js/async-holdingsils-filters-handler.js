/**
 * This part is intended to maintain filters processing ...
 * 
 * To make this JS work, you have to include async-holdingsils.js too ...
 * 
 */
var filterTypes = [ 'year', 'volume' ];

$(function() { // Append these eventListeners after DOM loaded ..
    filterTypes.forEach(function(filterType) {
	$("#" + filterType + "_filter").on('change', function() {
	    filterSelected(filterType, this.value);
	});
    });
});

function filterSelected(filter, value) {

    // This cycle basically selects the first option within all remaining
    // (nonselected) selects
    filterTypes.forEach(function(filterType) {
	if (filterType !== filter) {
	    $('select#' + filterType + '_filter').children().first().prop(
		    'selected', true);
	}
    });

    var selector = 'tr[data-type=holding][hidden=hidden]';

    if (value !== 'ALL') {

	// Hide rows not matching the filter selection
	$('tr[data-type=holding]:not([hidden=hidden]').attr('hidden', 'hidden');

	selector += '[data-' + filter + '=' + value + ']';
    }

    // Now unhide rows matching selected filters
    $(selector).removeAttr('hidden');

    // And now query the status of the unhidden
    getHoldingStatuses();
}
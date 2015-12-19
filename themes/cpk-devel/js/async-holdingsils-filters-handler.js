/**
 * This part is intended to maintain filters processing ...
 * 
 * To make this JS work, you have to include async-holdingsils.js too ...
 * 
 */
var holdingsILSfilters = {

    filterTypes : [ 'year', 'volume' ],

    selectors : {
	filters : {},
	tbody : null
    },

    init : function() {

	// Initialize table body selector for faster row resolving
	var tbodySelector = 'div#holdings-tab table tbody';
	holdingsILSfilters.selectors.tbody = $(tbodySelector);

	holdingsILSfilters.filterTypes.forEach(function(filterType) {

	    // Initialize the selector for current filter
	    holdingsILSfilters.selectors.filters[filterType] = holdingsILSfilters.selectors.tbody.siblings('caption').find("select#" + filterType + "_filter");

	    // Append onChange eventListener to all filter selects
	    function onChangeEvent() {
		holdingsILSfilters.filterSelected(filterType, this.value);
	    }

	    holdingsILSfilters.selectors.filters[filterType].on('change', onChangeEvent);
	});
    },

    filterSelected : function(filter, value) {

	// This cycle basically selects the first option within all
	// remaining
	// (nonselected) selects
	holdingsILSfilters.filterTypes.forEach(function(filterType) {
	    if (filterType !== filter) {

		var firstOption = holdingsILSfilters.selectors.filters[filterType].children().first();
		firstOption.prop('selected', true);
	    }
	});

	var selector = 'tr.hidden';

	if (value !== 'ALL') {

	    // Hide rows not matching the filter selection
	    // Note that we have to use class hidden due to mobile compatibility
	    holdingsILSfilters.selectors.tbody.children('tr:not(.hidden)').addClass('hidden');

	    selector += '[data-' + filter + '=' + value + ']';
	}

	// Now unhide rows matching selected filters
	holdingsILSfilters.selectors.tbody.children(selector).removeClass('hidden');

	// And now query the status of the unhidden
	getHoldingStatuses();
    },
};

// Call init on DOM load
$(holdingsILSfilters.init);
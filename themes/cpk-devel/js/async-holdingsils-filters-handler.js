/**
 * This part is intended to maintain filters processing ...
 * 
 * To make this JS work, you have to include async-holdingsils.js too ...
 * 
 */
var holdingsILSfilters = {

    activeFilter : undefined,

    filterTypes : [ 'year', 'volume' ],

    selectors : {
	filters : {},
	tbody : null
    },

    initialized : false,

    init : function() {
	if (holdingsILSfilters.initialized === false) {

	    // Initialize table body selector for faster row resolving
	    var tbodySelector = 'div#holdings-tab table tbody';
	    holdingsILSfilters.selectors.tbody = $(tbodySelector);

	    holdingsILSfilters.filterTypes.forEach(function(filterType) {

		// Initialize the selector for current filter
		holdingsILSfilters.selectors.filters[filterType] = holdingsILSfilters.selectors.tbody.siblings('caption').find(
			"select#" + filterType + "_filter");

		// Append onChange eventListener to all filter selects
		function onChangeEvent() {
		    holdingsILSfilters.filterSelected(filterType, this.value);
		}

		holdingsILSfilters.selectors.filters[filterType].on('change', onChangeEvent);
	    });

	    // now detect which filter is active
	    holdingsILSfilters.updateActiveFilter();

	    holdingsILSfilters.initialized = true;
	}
    },

    filterSelected : function(filter, value) {

	// This cycle basically selects the first option within all
	// remaining (nonselected) selects
	holdingsILSfilters.filterTypes.forEach(function(filterType) {
	    if (filterType !== filter) {

		var firstOption = holdingsILSfilters.selectors.filters[filterType].children().first();
		firstOption.prop('selected', true);
	    }
	});

	var selector = 'tr.hidden';

	if (value !== 'ALL') {

	    // Update activeFilter
	    holdingsILSfilters.activeFilter = {};
	    holdingsILSfilters.activeFilter[filter] = value;

	    // Hide rows not matching the filter selection
	    // Note that we have to use class hidden due to mobile compatibility
	    holdingsILSfilters.selectors.tbody.children('tr:not(.hidden)').addClass('hidden');

	    selector += '[data-' + filter + '=' + value + ']';
	} else {
	    // no filter selected now ..
	    holdingsILSfilters.activeFilter = undefined;
	}

	// Now unhide rows matching selected filters
	holdingsILSfilters.selectors.tbody.children(selector).removeClass('hidden');

	// And now query the status of the unhidden
	holdingsILS.getHoldingStatuses();
    },

    /**
     * Updates the holdingsILSfilters.activeFilter variable to an object where
     * key is the filter name & value is it's value or sets to undefined if no
     * filter is active
     * 
     * @param filterType
     *                (optional, defaults to true)
     */
    updateActiveFilter : function(filterType) {

	var foundAnything = false;

	var filterTypesLength = holdingsILSfilters.filterTypes.length;

	for (var i = 0; i < filterTypesLength; ++i) {

	    var filterType = holdingsILSfilters.filterTypes[i];

	    var filter = holdingsILSfilters.selectors.filters[filterType];

	    var selected = filter.children('[selected]');
	    var hasAnythingSelected = selected.length !== 0;

	    if (hasAnythingSelected) {

		var isFirstSelected = typeof holdingsILSfilters.selectors.filters[filterType].children().first().attr('selected') !== 'undefined';

		if (!isFirstSelected) {
		    // Yup, there is something selected & it is not the
		    // first
		    // option -> thus it is active

		    var value = selected.first().val();

		    holdingsILSfilters.activeFilter = {};
		    holdingsILSfilters.activeFilter[filterType] = value;

		    foundAnything = true;
		    break;
		}
	    }

	}

	if (foundAnything === false) {
	    // No filter is active -> undefine holdingsILSfilters.activeFilter
	    holdingsILSfilters.activeFilter = undefined;
	}
    },
};

// Call init on DOM load
$(holdingsILSfilters.init);
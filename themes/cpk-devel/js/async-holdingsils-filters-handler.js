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
	    holdingsILSfilters.detectActiveFilter();

	    holdingsILSfilters.initialized = true;
	}
    },

    filterSelected : function(filter, value) {

	holdingsILSfilters.updateActiveFilter(filter);

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

    /**
     * Updates the holdingsILSfilters.activeFilter variable to an object where
     * key is the filter name & value is it's value.
     * 
     * If you set undefineIfNotActive to true, there will be
     * holdingsILSfilters.activeFilter set to undefined if the filterType is not
     * active.
     * 
     * @param filterType
     * @param undefineIfNotActive
     *                (optional, defaults to true)
     * @returns {Boolean} - true if is active, false otherwise)
     */
    updateActiveFilter : function(filterType, undefineIfNotActive) {

	if (undefineIfNotActive === undefined) {
	    // defaults to true
	    undefineIfNotActive = true;
	}

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

		return true;
	    }
	}

	if (undefineIfNotActive === true) {
	    // No filter is active -> undefine holdingsILSfilters.activeFilter
	    holdingsILSfilters.activeFilter = undefined;
	}

	return false;
    },

    /**
     * Updates the holdingsILSfilters.activeFilter variable either to undefined
     * if no filter is active, or to an object where key is the filter name &
     * value is it's value
     */
    detectActiveFilter : function() {

	var foundAnything = false;

	var filterTypesLength = holdingsILSfilters.filterTypes.length;

	for (var i = 0; i < filterTypesLength; ++i) {

	    var filterType = holdingsILSfilters.filterTypes[i];

	    var isActiveThisOne = holdingsILSfilters.updateActiveFilter(filterType, false);

	    if (isActiveThisOne) {
		foundAnything = true;
		break;
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
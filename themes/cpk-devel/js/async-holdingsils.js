$(getHoldingStatuses); // Launch this function onLoad event

// Async holdings loader
function getHoldingStatuses(ids) {

	if (typeof ids !== 'object')
		ids = getHoldingsIds();
	
	if (ids.length != 0) {
		$
				.ajax({
					type : 'POST',
					url : '/AJAX/JSON?method=getHoldingsStatuses',
					dataType : 'json',
					async : true,
					// json object to sent to the authentication url
					data : {
						ids : ids
					},
					success : function(response) {
						processGetHoldingStatusesResponse(response);
					},
					error : function(msg) {
						console
								.error("async-holdingsils.js produced an error while doing AJAX:\n"
										+ msg);
					}
				})
	}
}

function getHoldingsIds(includingBeingLoadedIds) {

	if (typeof includingBeingLoadedIds === 'undefined')
		includingBeingLoadedIds = false;

	var ids = [];

	getAllNotLoadedHoldings(includingBeingLoadedIds).each(function() {
		ids.push($(this).attr('id'));

		// Add loading class so that we know about being it parsed
		$(this).addClass('loading');
	});

	return ids;
}

function getAllNotLoadedHoldings(includingBeingLoaded) {

	if (typeof includingBeingLoaded === 'undefined')
		includingBeingLoaded = true;

	var selector;

	if (includingBeingLoaded) {
		selector = 'tr[data-type=holding]:not(.loaded):not([hidden=hidden])';
	} else {
		selector = 'tr[data-type=holding]:not(.loading, .loaded):not([hidden=hidden])';
	}

	return $(selector);
}

function processGetHoldingStatusesResponse(r) {

	var data = r.data;

	if (typeof data.statuses !== 'undefined') {

		// Update the status
		$.each(data.statuses, function(key, value) {
			updateHoldingId(key, value);
		});

		if (data.remaining) {
			getHoldingStatuses(data.remaining);
		}
		else {
			getAllNotLoadedHoldings(true).each(function() {
				updateHoldingId(this, data, true);
			});
		}
	} else {

		// Show error messages
		getAllNotLoadedHoldings(true).each(function() {
			updateHoldingId(this, data, true);
		});
	}
}

function updateHoldingId(id, value, setUnknownLabel) {

	if (typeof setUnknownLabel === 'undefined')
		setUnknownLabel = false;

	var tableRow;

	if (!id)
		return null;

	if (typeof id === 'object') {

		tableRow = $(id);

	} else {

		// Escape special chars ..
		id = id.replace(/([.:])/g, '\\$1');

		tableRow = $("tr#" + id);
	}

	var statusDiv = tableRow.find('[data-type=item-status]')[0], icon = $(
			statusDiv).children('i'), label = $(statusDiv).children(
			'span.label');

	// Purge the loading icon
	icon.remove();

	var status = value.status;
	// Set status to the label
	if (typeof status !== 'undefined')
		label.text(status);
	else {
		label.text('unknown status');
		setUnknownLabel = true;
	}

	var availability = value.availability;

	if (typeof availability !== 'undefined') {
		var availabilitySpan = tableRow.find('[data-type=availability]')[0];

		availabilitySpan.textContent = availability;
	}

	var divLink = tableRow.find('[data-type=link]')[0];

	var dueDate = value.due_date;
	if (typeof dueDate !== 'undefined' && dueDate) {

		// We have some due date here ..
		var dueDateColumn = $(tableRow.children('[data-type=due_date]')[0]);
		dueDateColumn.text(dueDate);

		label.removeClass('label-primary').addClass('label-warning');

		if (typeof divLink !== 'undefined')
			divLink.remove();

	} else {

		if (setUnknownLabel) {
			label.removeClass('label-primary').addClass('label-unknown');

			if (typeof divLink !== 'undefined')
				divLink.remove();

		} else {
			var labelType = typeof value.label === 'undefined' ? 'label-success'
					: value.label;

			label.removeClass('label-primary').addClass(labelType);

			toBeBroken: if (typeof divLink !== 'undefined') {
				// Show hidden link
				var linkSpan = divLink.children[0].children[0], holdType = value.hold_type;

				if (typeof holdType === 'undefined') {
					holdType = 'Reserve';
				} else if (holdType === 'false') {
					divLink.remove();
					break toBeBroken;
				}

				linkSpan.innerHTML = holdType;

				$(divLink).removeAttr('hidden');
			}
		}
	}

	tableRow.removeClass('loading').addClass('loaded');

}
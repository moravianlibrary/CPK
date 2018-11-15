$(function() { // Onload DOM ..
    $('table[id].table.table-striped').each(function() {
	var cat_username = $(this).attr('id');

	fetchFines(cat_username);
    })
    
    goToAnchorIfAny();
});

function goToAnchorIfAny() {
    var hasAnchor = window.location.href.match(/Fines[/]?#[a-z]+$/);
    if (hasAnchor !== null) {
	window.location = window.location.href;
    }
}

function fetchFines(cat_username) {
    $.ajax({
	type : 'POST',
	url : '/AJAX/JSON?method=getMyFines',
	dataType : 'json',
	async : true,
	// json object to sent to the authentication url
	data : {
	    cat_username : cat_username
	},
	success : function(response) {
	    updateFinesTable(response);
	    goToAnchorIfAny();
	}
    })
}

function updateFinesTable(response) {
    
    var data = response.data, status = response.status;
    if (typeof data.dg !== 'undefined') {
        fillDebug(data.dg);
    }

    var cat_username = data.cat_username, parentTable = {};

    if (cat_username) {
	parentTable = $('table[id="' + cat_username + '"].table.table-striped');
    }

    if (parentTable.length) {
	var tableBody = parentTable.children('tbody'), divLoadingInfo = parentTable
		.find('div[data-type=loading-info]');

	if (status == "OK") {

	    var counter = new Counter();

	    var moneyFormat = tableBody.attr('data-money-format');
	    var summaryRow = tableBody.children().next();

	    var finesAppended = 0;

	    // Remove the loading caption
	    divLoadingInfo.remove();

	    // Update only recieved data
	    $.each(data.fines,
		    function(key, fine) {
			if (typeof fine == 'object') {

			    var tableRow = getTableRowFromFine(tableBody, fine,
				    counter);

			    summaryRow.before(tableRow);
			    ++finesAppended;
			}
		    });

	    if (finesAppended) {
		// Show total sum ..
		var totalSum = counter.getTotalSum();

		var formattedMoney = formatMoney(moneyFormat, totalSum);
		summaryRow.find('td[data-type=sum]').text(formattedMoney);

		if (!(typeof data.paymentUrl === "undefined")) {
			if (data.paymentUrl !== null)
				summaryRow.after('<tr><td></td><td></td><td></td><td><a class="btn btn-primary btn-sm" id="pay-button" href="' + data.paymentUrl + '">' + data.payButtonText + '</a></td></tr>');
		}
		// Unhide the table
		tableBody.removeAttr('hidden');

		// Purge <caption>
		parentTable.children('caption').remove();
	    } else {
		// No fines were recieved ..
		parentTable.find('div[data-type=without-fines-info]').addClass(
			'label label-info').removeAttr('hidden');

		// Purge <tbody>
		tableBody.remove();
	    }
	    
	    var count = $( 'table[id="' + cat_username + '"] tr.excluded' ).size();
	    if (count > 0) {
            $( 'tr[id="summary_' + cat_username + '"]' )
                .before(`
                     <tr>
                       <td colspan='4' class='text-center'>
                         <a id='${cat_username}' class='toggler'>
                           <i class='pr-interface-arrowbottom4' title='${VuFind.translate("show_others")}'> </i>
                         </a>
                       </td>
                     </tr>`);
	    }
	    $( 'a[id="' + cat_username + '"].toggler' ).click( function() {
	    	$(this).toggleClass('more');
	        if ($(this).is(".more")){
	        	$(this)
					.html(`<i class='pr-interface-arrowtop4' title='${VuFind.translate("hide_others")}'> </i>`);
	        	$( 'table[id="' + cat_username + '"] tr.excluded' ).removeClass('hidden');
	        } else {
                $(this)
					.html(`<i class='pr-interface-arrowbottom4' title='${VuFind.translate("show_others")}'> </i>`);
	        	$( 'table[id="' + cat_username + '"] tr.excluded' ).addClass('hidden');
	        	window.location = '/MyResearch/Fines#' + parentTable.parent().attr('id');
	        }
	    });

	} else {
	    var message = response.data.message;

	    // Remove loading icon
	    divLoadingInfo.children('i').remove();

	    var label = divLoadingInfo.children('span.label');

	    // Set red background to the label
	    label.addClass('label label-danger');

	    // Print the message
	    if (message) {
		label.html(message);
	    } else {
		label.html('Unknown problem occured');
	    }

	    // Purge <tbody>
	    tableBody.remove();
	}
    } else {

	if (typeof response === "object" && typeof response.toSource !== "undefined") // Only Mozilla can convert object to source string ..
	    response = response.toSource();
	
	console.error("cat_username from the response was not found on this page .. cannot update the fines table! " + response, arguments);
    }
}

/**
 * Crafts a 'tr' element with all the 'td's based on passed 'th' elements within
 * 'tbody' element.
 * 
 * All the 'th' elements should have an attribute called 'data-key' with value,
 * which determines what data should be placed into that column.
 * 
 * @param DOM tableBody
 * @param object fine
 * @param Counter counter
 * @returns DOM tr
 */
function getTableRowFromFine(tableBody, fine, counter) {
    var tr = $('<tr>');
    if (fine.excluded == true) tr.addClass('excluded hidden');

    var ths = tableBody.children().first().children();
    var moneyFormat = tableBody.attr('data-money-format');

    $.each(ths, function() {
	var tableCell = $('<td>');

	var dataKey = $(this).attr('data-key');
	var dataVal = fine[dataKey];

	if (typeof dataVal != 'undefined') {

	    if (dataKey == 'title') {
		if (dataVal && typeof fine['id'] != 'undefined') {
		    // Create a link if id is available
		    var anchor = $('<a href="/Record/' + fine['id'] + '">');
		    anchor.text(dataVal);

		    dataVal = anchor;
		}
	    } else if (dataKey == 'amount' || dataKey == 'balance') {
		var sum = parseInt(dataVal) / 100;

		if (dataKey == 'amount')
		    counter.addSum(sum);

		dataVal = formatMoney(moneyFormat, sum);
	    }

	    tableCell.html(dataVal);
	} else if (dataKey == 'title') {
	    // If title is empty, show nothing
	    tableCell.text(dataVal);
	}

	tr.append(tableCell);
    });

    return tr;
}

/**
 * Helper for counting total sum.
 */
var Counter = (function() {

    var totalSum;

    function Counter() {
	totalSum = 0
    }

    Counter.prototype.getTotalSum = function() {
	return totalSum;
    };

    Counter.prototype.addSum = function(sum) {
	totalSum += sum;
    };

    return Counter;
})();

/**
 * Returns a string where is "VALUE" within the moneyFormat variable replaced
 * with the sum forced to show two decimal places.
 */
var formatMoney = function(moneyFormat, sum) {
    return moneyFormat.replace(/VALUE/g, sum.toFixed(2));
}

$(function() { // Onload DOM ..
    $('table[id].table.table-striped').each(function() {
	var cat_username = $(this).attr('id');

	fetchFines(cat_username);
    })
});

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
	}
    })
}

function updateFinesTable(response) {
    var data = response.data, status = response.status;

    var cat_username = data.cat_username, parentTable = {};

    if (cat_username) {
	parentTable = $('table[id="' + cat_username + '"].table.table-striped');
    }

    if (parentTable.length) {
	if (status == "OK") {

	    var counter = new Counter();

	    var tableBody = parentTable.children('tbody');
	    var moneyFormat = tableBody.attr('data-money-format');
	    var summaryRow = tableBody.children().next();

	    var finesAppended = 0;

	    // Remove the loading caption
	    parentTable.find('div[data-type=loading-info]').remove();

	    // Update only recieved data
	    $.each(data.fines, function(key, fine) {
		if (typeof fine == 'object') {

		    var tableRow = getTableRowFromFine(tableBody, fine, counter);

		    summaryRow.before(tableRow);
		    ++finesAppended;
		}
	    });

	    if (finesAppended) {
		var totalSum = counter.getTotalSum();

		var formattedMoney = formatMoney(moneyFormat, totalSum);
		summaryRow.find('td[data-type=sum]').text(formattedMoney);

		tableBody.removeAttr('hidden');
	    } else {
		// No fines were recieved ..
		parentTable.find('div[data-type=without-fines-info]').addClass(
			'label label-info').removeAttr('hidden');
	    }

	} else {
	    var message = response.data.message, divLoadingInfo = parentTable
		    .find('div[data-type=loading-info]');

	    // Remove loading icon
	    divLoadingInfo.children('i').remove();

	    var label = divLoadingInfo.children('span.label');

	    // Set red background to the label
	    label.removeClass('label-primary').addClass('label-danger');

	    // Print the message
	    if (message) {
		label.html(message);
	    } else {
		label.html('Unknown problem occured');
	    }
	}
    }
}

/**
 * Crafts a 'tr' element with all the 'td's based on passed 'th' elements within 'tbody' element.
 * 
 * All the 'th' elements should have an attribute called 'data-key' with value,
 * which determines what data should be placed into that column.
 * 
 * @param tableBody
 * @param fine
 * @param counter
 * @returns DOM object tr
 */
function getTableRowFromFine(tableBody, fine, counter) {
    var tr = $('<tr>');
    
    var ths = tableBody.children().first().children();
    var moneyFormat = tableBody.attr('data-money-format');

    $.each(ths, function() {
	var tableCell = $('<td>');

	var dataKey = $(this).attr('data-key');
	var dataVal = fine[dataKey];
	
	var unknownTitle = tableBody.attr('data-title-unknown');

	if (typeof dataVal != 'undefined') {

	    if (dataKey == 'title') {
		if (typeof fine['id'] != 'undefined' && dataVal) {
		    // Create a link if id is available
		    var anchor = $('<a href="/Record/' + fine['id'] + '">');
		    anchor.text(dataVal);

		    dataVal = anchor;
		} else {
		    // If title is empty, set the string to unknown
		    dataVal = unknownTitle;
		}
	    } else if (dataKey == 'amount' || dataKey == 'balance') {
		var sum = parseInt(dataVal) / 100;

		if (dataKey == 'amount')
		    counter.addSum(sum);

		dataVal = formatMoney(moneyFormat, sum);
	    }

	    tableCell.html(dataVal);
	} else if (dataKey == 'title') {
	    // If title is empty, set the string to unknown
	    dataVal = unknownTitle;
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
    ;

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
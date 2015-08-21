var cookies_agreed = function(yesNo) {

    if (yesNo) {
        var date = new Date();
        date.setFullYear(date.getFullYear() + 10);
        document.cookie = 'eu-cookies=1; path=/; expires=' + date.toGMTString();

        $('.eu-cookies').remove();
    } else {
        document.cookie = 'eu-cookies=1; path=/';

        // Do not remove the div as the user didn't agree
    }
}

function isRecordPage() {
    return document.location.pathname.match(/\/Record\/\w+[.].*/) != null;
}

if (isRecordPage()) {
    $(getHoldingStatuses());
}

function getHoldingsIds(allNotLoaded) {
    
    if (Object.prototype.toString.call(allNotLoaded) === "[object Undefined]")
        allNotLoaded = false;
    
    ids = [];
    var selector;
    
    if (allNotLoaded) {
        selector = "div#holdings-tab tbody tr:not(.loaded)";
    } else {
        selector = "div#holdings-tab tbody tr:not(.loading, .loaded)"
    }
    
    $(selector).each(function() {
        ids.push($(this).attr('id'));

        // Add loading class so that we know about being it parsed
        $(this).addClass('loading');
    });
    return ids;
}

function updateHoldingId(id, value, isItBad) {
    
    if (typeof isItBad === 'undefined')
        isItBad = false;
    
    var tableRow = $("tr#" + id.replace(/([.:])/g, '\\$1')),
        statusDiv = tableRow.find('div')[1],
        icon = $(statusDiv).children('i'),
        label = $(statusDiv).children('span.label');

    // Purge the loading icon
    icon.remove();

    var status = value.status;
    // Set status to the label
    if (typeof status !== 'undefined')
        label.text(status);
    else {
        label.text('unknown status');
        isItBad = true;
    }
    
    var dueDate = value.due_date;
    if (typeof dueDate !== 'undefined') {
        var dueDateColumn = $(tableRow.children('td')[1]);
        dueDateColumn.text(dueDate);
    }

    if (isItBad) {
        label.removeClass('label-primary').addClass('label-danger');
    } else {
        label.removeClass('label-primary').addClass('label-success');
    }
    tableRow.removeClass('loading').addClass('loaded');

}

function processGetHoldingStatusesResponse(r) {

    var data = r.data;

    if (typeof data.statuses !== 'undefined') {
        
        // Update the status
        $.each(data.statuses, function(key, value) {
            updateHoldingId(key, value);
        });

        if (data.remaining) {
            getHoldingStatuses()(data.remaining);
        }
    } else {
        
        // Show error messages
        var ids = getHoldingsIds(true);
        $.each(ids, function() {
            updateHoldingId(this, data, true);
        });
    }
}

// Async holdings loader
// TODO: Make the request cancellable after user chooses any filter
function getHoldingStatuses() {
    return function(ids) {

        if (document.location.pathname.indexOf('Record/') || ids === true) {

            if (Object.prototype.toString.call(ids) !== '[object Object]')
                ids = getHoldingsIds();

            var ajaxResponse = $.ajax
            ({
                type: "POST",
                url: '/AJAX/JSON?method=getHoldingsStatuses',
                dataType: 'json',
                async: true,
                //json object to sent to the authentication url
                data: {ids: ids},
                success: function(response) {
                    processGetHoldingStatusesResponse(response);
                }
            })
        }
    }
}
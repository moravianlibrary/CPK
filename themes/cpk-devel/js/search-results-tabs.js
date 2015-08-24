// resultid - selector of result div (order on resultpage)
// recordid - id of library and specific record
function ajaxLoadListOfLibraries(resultSelector, recordid) {
  var urlroot = "/Record/" + recordid;

  $.ajax({
    url: path + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: "dedupedrecords"},
    success: function(data) {
      var tabsContentSelector = " .want-it-tabs-row1";
      $(resultSelector + tabsContentSelector + ' #dedupedrecords').html(data);
      getHoldingStatuses()();
    }
  });
  return false;
}

// resultid - selector of result div (order on resultpage)
// recordid - id of library and specific record
function ajaxLoadHoldings(resultSelector, urlroot) {
  //var urlroot = "/Record/" + recordid;

  $.ajax({
    url: path + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: "holdings"},
    success: function(data) {
      var tabsContentSelector = " .want-it-tabs-row2";
      $(resultSelector + tabsContentSelector + ' #holdings').html(data);
      getHoldingStatuses()();
    }
  });
  return false;
}





// resultid - selector of result div (order on resultpage)
// tabid - id of tab to show
// recordid - id of library and specific record
function ajaxLoadWantItContent(resultSelector, tabid, recordid) {
  var urlroot = "/Record/" + recordid;
  $.ajax({
    url: path + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: tabid},
    success: function(data) {
      var tabsContentSelector = " .want-it-tabs-row2";
      $(resultSelector + tabsContentSelector + ' #'+tabid).html(data);
      getHoldingStatuses()(true);
    }
  });
  return false;
}

// resultid - selector of result div (order on resultpage)
// tabid - id of tab to show
// recordid - id of library and specific record
function ajaxLoadWantItLibraryContent(resultSelector, tabid, recordid) {
  var urlroot = "/Record/" + recordid;
  $.ajax({
    url: path + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: "dedupedrecords"},
    success: function(data) {
      var tabsContentSelector = " .want-it-tabs-row1";
      $(resultSelector + tabsContentSelector + ' #'+tabid).html(data);
      getHoldingStatuses()();
    }
  });
  return false;
}



$(document).ready(function() {
  $(document).on('click','.want-it a#holdings',function() {

    var result = $(this).closest('.result');
    var resultid = result.attr('id');
    var tabid = $(this).attr('id').toLowerCase();
    var recordid = result.find('.hiddenId')[0].value;


    tabid = "dedupedrecords";

    var resultSelector = '#'+resultid;
    var tabsContentSelector = " .want-it-tabs-row1";
    var clickedTabIdSelector = ' #' + tabid;
    var clickedTabIdSelector = ' #dedupedrecords';

    if ($(resultSelector + tabsContentSelector + clickedTabIdSelector).length > 0) { // tab je již načtený?
      if( $(resultSelector + tabsContentSelector + clickedTabIdSelector + '.active').attr('id') == tabid) { //tab je již zobrazený?
        $(resultSelector + ' .want-it-tab').removeClass('active'); //skryj všechny taby (včetně seznamu knihoven)
        return false;
      }
      else { //tab je načtený, ale není zobrazený
        $(resultSelector + tabsContentSelector + ' .want-it-tab.active').removeClass('active'); //skryj aktální tab
        $(resultSelector + tabsContentSelector + clickedTabIdSelector).addClass('active'); //zobraz seznam knihoven
        return false;
      }
    } else { //kliknuto na nenačtený tab
      $(resultSelector + tabsContentSelector).append('<div class="want-it-tab" id="' + tabid + '"><i class="fa fa-spinner fa-spin"></i> ' + vufindString['loading'] + '...</div>'); //nastav placeholder
      $(resultSelector + tabsContentSelector + ' .want-it-tab.active').removeClass('active'); //skryj aktuální tab
      $(resultSelector + tabsContentSelector + clickedTabIdSelector).addClass('active'); // zobraz placeholder
      return ajaxLoadListOfLibraries(resultSelector, recordid); //asynchroně načti obsah tabu
    }
  });

  $(document).on('click','.want-it a#eversion',function() {

    var result = $(this).closest('.result');
    var resultid = result.attr('id');
    var tabid = $(this).attr('id').toLowerCase();
    var recordid = result.find('.hiddenId')[0].value;


    tabid = "eversion";

    var resultSelector = '#'+resultid;
    var tabsContentSelector = " .want-it-tabs-row1";
    var clickedTabIdSelector = ' #' + tabid;
    var clickedTabIdSelector = ' #eversion';

    if ($(resultSelector + tabsContentSelector + clickedTabIdSelector).length > 0) { // tab je již načtený?
      if( $(resultSelector + tabsContentSelector + clickedTabIdSelector + '.active').attr('id') == tabid) { //kliknuto na aktualne rozbalenou moznost -> sbal ji
        $(resultSelector + ' .want-it-tab').removeClass('active'); //skryj všechny taby (včetně seznamu knihoven)
      return false;
      }
      else { //kliknuto na již načtený tab -> přepni na něj
        $(resultSelector + tabsContentSelector + ' .want-it-tab.active').removeClass('active');
        $(resultSelector + tabsContentSelector + clickedTabIdSelector).addClass('active');
        return false;
      }
    } else { //kliknuto na nenačtený tab -> načti a přepni
      $(resultSelector + tabsContentSelector).append('<div class="want-it-tab" id="' + tabid + '">E-version placeholder</div>');
      $(resultSelector + tabsContentSelector + ' .want-it-tab.active').removeClass('active');
      $(resultSelector + tabsContentSelector + clickedTabIdSelector).addClass('active');
    }
  });


  $(document).on('click','.want-it-library-link',function() {
    var result = $(this).closest('.result');
    var resultid = result.attr('id');
    var recordid = result.find('.hiddenId')[0].value;

    var tabid = "holdings";

    var resultSelector = '#' + resultid;
    var tabsContentSelector = " .want-it-tabs-row2";
    var clickedTabIdSelector = ' #' + tabid;
    var clickedTabIdSelector = ' #holdings';

    $('.want-it-library-link').removeClass("btn-primary");
    $(this).addClass("btn-primary");


    if ($(resultSelector + tabsContentSelector + clickedTabIdSelector).length > 0) { // tab je již načtený?
      if ($(resultSelector + tabsContentSelector + clickedTabIdSelector + '.active').attr('id') == tabid) { //kliknuto na aktuální knihovnu
        $(resultSelector + tabsContentSelector + ' .want-it-tab').removeClass('active'); //sbal jednotky aktuální knihovny
        return false;
      }
      else { //kliknuto na již načtený tab -> přepni na něj
        $(resultSelector + tabsContentSelector + ' .want-it-tab.active').removeClass('active');
        $(resultSelector + tabsContentSelector + clickedTabIdSelector).addClass('active');
        return false;
      }
    } else { //kliknuto na nenačtený tab -> načti a přepni
      $(resultSelector + tabsContentSelector).append('<div class="want-it-tab" id="' + tabid + '"><i class="fa fa-spinner fa-spin"></i> ' + vufindString['loading'] + '...</div>');
      $(resultSelector + tabsContentSelector + ' .want-it-tab.active').removeClass('active');
      $(resultSelector + tabsContentSelector + clickedTabIdSelector).addClass('active');
      var urlroot = $(this).attr("href");
      return ajaxLoadHoldings(resultSelector, urlroot);
    }
  });
});
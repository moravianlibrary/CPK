// resultid - selector of result div (order on resultpage)
// tabid - id of tab to show
// recordid - id of library and specific record
function ajaxLoadWantItContent(resultid, tabid, recordid) {
  var urlroot = "/Record/" + recordid;
  $.ajax({
    url: path + urlroot + '/AjaxTab',
    type: 'POST',
    data: {tab: tabid},
    success: function(data) {
      $('#'+resultid+' .want-it-tabs .tab-pane.active').removeClass('active');
      $('#'+resultid+' #'+tabid+'-tab').html(data).addClass('active');
      $('#'+resultid+' #'+tabid).tab('show');
      getHoldingStatuses()(true);
    }
  });
  return false;
}



$(document).ready(function() {
  $(document).on('click','.want-it a',function() {

    var result = $(this).closest('.result');
    var resultid = result.attr('id');
    var tabid = $(this).attr('id').toLowerCase();
    var recordid = result.find('.hiddenId')[0].value;

    if ($('#'+resultid+' #' + tabid + '-tab').length > 0) { // tab je již načtený?
      if( $('#'+resultid+' #' + tabid + '-tab.active').attr('id') == tabid + "-tab") { //kliknuto na aktualne rozbalenou moznost -> sbal ji
        $('#'+resultid+' .want-it-tabs .want-it-tab').removeClass('active');
        return false;
      }
      else { //kliknuto na již načtený tab -> přepni na něj
        $('#' + resultid + ' .want-it-tabs .want-it-tab.active').removeClass('active');
        $('#' + resultid + ' #' + tabid + '-tab').addClass('active');
        $('#' + resultid + ' #' + tabid).tab('show');
        return false;
      }
    } else { //kliknuto na nenačtený tab -> načti a přepni
      $('#'+resultid+' .want-it-tabs').append('<div class="want-it-tab" id="' + tabid + '-tab"><i class="fa fa-spinner fa-spin"></i> ' + vufindString['loading'] + '...</div>');
      $('#'+resultid+' .want-it-tabs .want-it-tab.active').removeClass('active');
      $('#'+resultid+' #' + tabid + '-tab').addClass('active');
      return ajaxLoadWantItContent(resultid, tabid, recordid);
    }
  });

});
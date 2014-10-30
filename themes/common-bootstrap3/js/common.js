/*global checkSaveStatuses, console, extractSource, hexEncode, Lightbox, path, rc4Encrypt, refreshCommentList, vufindString */

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value){
  if (value) {
    return jQuery('<div />').text(value).html();
  } else {
    return '';
  }
}
function extractClassParams(str) {
  str = $(str).attr('class');
  if (typeof str === "undefined") return [];
  var params = {};
  var classes = str.split(/\s+/);
  for(var i = 0; i < classes.length; i++) {
    if (classes[i].indexOf(':') > 0) {
      var pair = classes[i].split(':');
      params[pair[0]] = pair[1];
    }
  }
  return params;
}
function jqEscape(myid) {
  return String(myid).replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, "\\$&");
}
function html_entity_decode(string, quote_style)
{
  var hash_map = {},
    symbol = '',
    tmp_str = '',
    entity = '';
  tmp_str = string.toString();

  delete(hash_map['&']);
  hash_map['&'] = '&amp;';
  hash_map['>'] = '&gt;';
  hash_map['<'] = '&lt;';

  for (symbol in hash_map) {
    entity = hash_map[symbol];
    tmp_str = tmp_str.split(entity).join(symbol);
  }
  tmp_str = tmp_str.split('&#039;').join("'");

  return tmp_str;
}

// Turn GET string into array
function deparam(url) {
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    var name = decodeURIComponent(pair[0]);
    if(name.length == 0) {
      continue;
    }
    if(name.substring(name.length-2) == '[]') {
      name = name.substring(0,name.length-2);
      if(!request[name]) {
        request[name] = [];
      }
      request[name].push(decodeURIComponent(pair[1]));
    } else {
      request[name] = decodeURIComponent(pair[1]);
    }
  }
  return request;
}

// Sidebar
function moreFacets(id) {
  $('.'+id).removeClass('hidden');
  $('#more-'+id).addClass('hidden');
}
function lessFacets(id) {
  $('.'+id).addClass('hidden');
  $('#more-'+id).removeClass('hidden');
}

// Advanced facets
function updateOrFacets(url, op) {
  window.location.assign(url);
  var list = $(op).parents('ul');
  var header = $(list).find('li.nav-header');
  list.html(header[0].outerHTML+'<div class="alert alert-info">'+vufindString.loading+'...</div>');
}
function setupOrFacets() {
  $('.facetOR').find('.icon-check').replaceWith('<input type="checkbox" checked onChange="updateOrFacets($(this).parent().parent().attr(\'href\'), this)"/>');
  $('.facetOR').find('.icon-check-empty').replaceWith('<input type="checkbox" onChange="updateOrFacets($(this).parent().attr(\'href\'), this)"/> ');
}


$(document).ready(function() {
  // support "jump menu" dropdown boxes
  $('select.jumpMenu').change(function(){ $(this).parent('form').submit(); });

  // Highlight previous links, grey out following
  $('.backlink')
    .mouseover(function() {
      // Underline back
      var t = $(this);
      do {
        t.css({'text-decoration':'underline'});
        t = t.prev();
      } while(t.length > 0);
      // Mute ahead
      t = $(this).next();
      do {
        t.css({'color':'#999'});
        t = t.next();
      } while(t.length > 0);
    })
    .mouseout(function() {
      // Underline back
      var t = $(this);
      do {
        t.css({'text-decoration':'none'});
        t = t.prev();
      } while(t.length > 0);
      // Mute ahead
      t = $(this).next();
      do {
        t.css({'color':''});
        t = t.next();
      } while(t.length > 0);
    });

  // Search autocomplete
  $('.autocomplete').typeahead(
    {
      highlight: true,
      minLength: 3
    }, {
      displayKey:'val',
      source: function(query, cb) {
        var searcher = extractClassParams('.autocomplete');
        $.ajax({
          url: path + '/AJAX/JSON',
          data: {
            q:query,
            method:'getACSuggestions',
            searcher:searcher['searcher'],
            type:$('#searchForm_type').val()
          },
          dataType:'json',
          success: function(json) {
            if (json.status == 'OK' && json.data.length > 0) {
              var datums = [];
              for (var i=0;i<json.data.length;i++) {
                datums.push({val:json.data[i]});
              }
              cb(datums);
            } else {
              cb([]);
            }
          }
        });
      }
    }
  );

  // Checkbox select all
  $('.checkbox-select-all').change(function() {
    elm = $(this).closest('form').find('.checkbox-select-item');
    newVal = $(this).prop('checked');
    console.log("newVal: " + newVal);
    $(elm).each(function() {
      oldVal = $(this).prop('checked');
      if (newVal != oldVal) {
        $(this).prop('checked', newVal);
        $(this).change();
      }
    });
  });
  
  //disable AJAX on click on cart
  $(document).ready(function() {
      $(window).load(function() {
      	$('#cartItems').off("click");
      });
      $(this).closest('form').find('.checkbox-select-item').each(function() {
        this.checked = false;
      });
  });

  // handle QR code links
  $('a.qrcodeLink').click(function() {
    if ($(this).hasClass("active")) {
      $(this).html(vufindString.qrcode_show).removeClass("active");
    } else {
      $(this).html(vufindString.qrcode_hide).addClass("active");
    }
    $(this).next('.qrcode').toggleClass('hidden');
    return false;
  });

  // Print
  var url = window.location.href;
  if(url.indexOf('?' + 'print' + '=') != -1  || url.indexOf('&' + 'print' + '=') != -1) {
    $("link[media='print']").attr("media", "all");
    $(document).ajaxStop(function() {
      window.print();
    });
    // Make an ajax call to ensure that ajaxStop is triggered
    $.getJSON(path + '/AJAX/JSON', {method: 'keepAlive'});
  }

  // Advanced facets
  setupOrFacets();
  // Cart functionality
  refreshCartItems();

  $('.jt').tooltip({
	  placement: 'bottom',
	  html: true
  });
  
  $('input#requiredByDate, input#last-interest-date').datepicker({
    format: "dd.mm.yyyy",
    weekStart: 1,
    language: "cs"
  });
  
});

function updateCart(item) {
  value = $(item).attr('value');
  values = value.split('|');
  source = values[0];
  id = values[1];
  if ($(item).prop("checked")) {
    addItemToCart(id, source);
  } else {
    removeItemFromCart(id, source);
  }
}

function refreshCartItems() {
  items = getFullCartItems();
  $('.checkbox-select-all').closest('form').find('.checkbox-select-item').each(function(index) {
    if (items.indexOf($(this).attr('value')) >= 0) {
      $(this).attr('checked', true);
    }
  });
}

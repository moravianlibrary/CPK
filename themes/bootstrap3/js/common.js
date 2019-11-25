/*global btoa, console, hexEncode, isPhoneNumberValid, Lightbox, rc4Encrypt, unescape, VuFind */

function VuFindNamespace(p, s, dsb) {
  var defaultSearchBackend = dsb;
  var path = p;
  var strings = s;

  var getDefaultSearchBackend = function() { return defaultSearchBackend; };
  var getPath = function() { return path; };
  var translate = function(op) { return strings[op] || op; };

  return {
    getDefaultSearchBackend: getDefaultSearchBackend,
    getPath: getPath,
    translate: translate
  };
}

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value) {
  if (value) {
    return jQuery('<div />').text(value).html();
  } else {
    return '';
  }
}
function extractClassParams(str) {
  str = $(str).attr('class');
  if (typeof str === "undefined") {
    return [];
  }
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
// Turn GET string into array
function deparam(url) {
  if(!url.match(/\?|&/)) {
    return [];
  }
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    var name = decodeURIComponent(pair[0].replace(/\+/g, ' '));
    if(name.length == 0) {
      continue;
    }
    if(name.substring(name.length-2) == '[]') {
      name = name.substring(0,name.length-2);
      if(!request[name]) {
        request[name] = [];
      }
      request[name].push(decodeURIComponent(pair[1].replace(/\+/g, ' ')));
    } else {
      request[name] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
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

// Phone number validation
function phoneNumberFormHandler(numID, regionCode) {
  var phoneInput = document.getElementById(numID);
  var number = phoneInput.value;
  var valid = isPhoneNumberValid(number, regionCode);
  if(valid != true) {
    if(typeof valid === 'string') {
      valid = VuFind.translate(valid);
    } else {
      valid = VuFind.translate('libphonenumber_invalid');
    }
    $(phoneInput).siblings('.help-block.with-errors').html(valid);
    $(phoneInput).closest('.form-group').addClass('sms-error');
  } else {
    $(phoneInput).closest('.form-group').removeClass('sms-error');
    $(phoneInput).siblings('.help-block.with-errors').html('');
  }
}

// Lightbox
/*
 * This function adds jQuery events to elements in the lightbox
 *
 * This is a default open action, so it runs every time changeContent
 * is called and the 'shown' lightbox event is triggered
 */
function bulkActionSubmit($form) {
  var button = $form.find('[type="submit"][clicked=true]');
  var submit = button.attr('name');
  var checks = $form.find('input.checkbox-select-item:checked');
  if(checks.length == 0 && submit != 'empty') {
    Lightbox.displayError(VuFind.translate('bulk_noitems_advice'));
    return false;
  }
  if (submit == 'print') {
    //redirect page
    var url = VuFind.getPath() + '/Records/Home?print=true';
    for(var i=0;i<checks.length;i++) {
      url += '&id[]='+checks[i].value;
    }
    document.location.href = url;
  } else {
    $('#modal .modal-title').html(button.attr('title'));
    Lightbox.titleSet = true;
    Lightbox.submit($form, Lightbox.changeContent);
  }
  return false;
}

function registerLightboxEvents() {
  var modal = $("#modal");
  // New list
  $('#make-list').click(function() {
    var get = deparam(this.href);
    get['id'] = 'NEW';
    return Lightbox.get('MyResearch', 'EditList', get);
  });
  // New account link handler
  $('.createAccountLink').click(function() {
    var get = deparam(this.href);
    return Lightbox.get('MyResearch', 'Account', get);
  });
  $('.back-to-login').click(function() {
    Lightbox.getByUrl(Lightbox.openingURL);
    return false;
  });
  // Select all checkboxes
  $(modal).find('.checkbox-select-all').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $(modal).find('.checkbox-select-item').change(function() {
    $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
  });
  // Highlight which submit button clicked
  $(modal).find("form [type=submit]").click(function() {
    // Abort requests triggered by the lightbox
    $('#modal .fa-spinner').remove();
    // Remove other clicks
    $(modal).find('[type="submit"][clicked=true]').attr('clicked', false);
    // Add useful information
    $(this).attr("clicked", "true");
    // Add prettiness
    if($(modal).find('.has-error,.sms-error').length == 0 && !$(this).hasClass('dropdown-toggle')) {
      $(this).after(' <i class="fa fa-spinner fa-spin"></i> ');
    }
  });
  /**
   * Hide the header in the lightbox content
   * if it matches the title bar of the lightbox
   */
  var header = $('#modal .modal-title').html();
  var contentHeader = $('#modal .modal-body h2');
  contentHeader.each(function(i,op) {
    if (op.innerHTML == header) {
      $(op).hide();
    }
  });
}

function refreshPageForLogin() {
  window.location.reload();
}

function newAccountHandler(html) {
  Lightbox.addCloseAction(refreshPageForLogin);
  var params = deparam(Lightbox.openingURL);
  if (params['subaction'] == 'UserLogin') {
    Lightbox.close();
  } else {
    Lightbox.getByUrl(Lightbox.openingURL);
    Lightbox.openingURL = false;
  }
  return false;
}

// This is a full handler for the login form
function ajaxLogin(form) {
  Lightbox.ajax({
    url: VuFind.getPath() + '/AJAX/JSON?method=getSalt',
    dataType: 'json',
    success: function(response) {
      if (response.status == 'OK') {
        var salt = response.data;

        // extract form values
        var params = {};
        for (var i = 0; i < form.length; i++) {
          // special handling for password
          if (form.elements[i].name == 'password') {
            // base-64 encode the password (to allow support for Unicode)
            // and then encrypt the password with the salt
            var password = rc4Encrypt(
                salt, btoa(unescape(encodeURIComponent(form.elements[i].value)))
            );
            // hex encode the encrypted password
            params[form.elements[i].name] = hexEncode(password);
          } else {
            params[form.elements[i].name] = form.elements[i].value;
          }
        }

        // login via ajax
        Lightbox.ajax({
          type: 'POST',
          url: VuFind.getPath() + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: params,
          success: function(response) {
            if (response.status == 'OK') {
              Lightbox.addCloseAction(refreshPageForLogin);
              // and we update the modal
              var params = deparam(Lightbox.lastURL);
              if (params['subaction'] == 'UserLogin') {
                Lightbox.close();
              } else {
                Lightbox.getByUrl(
                  Lightbox.lastURL,
                  Lightbox.lastPOST,
                  Lightbox.changeContent
                );
              }
            } else {
              Lightbox.displayError(response.data);
            }
          }
        });
      } else {
        Lightbox.displayError(response.data);
      }
    }
  });
}

// Ready functions
function setupOffcanvas() {
  if($('.sidebar').length > 0) {
    $('[data-toggle="offcanvas"]').click(function () {
      $('body.offcanvas').toggleClass('active');
      var active = $('body.offcanvas').hasClass('active');
      var right = $('body.offcanvas').hasClass('offcanvas-right');
      if((active && !right) || (!active && right)) {
        $('.offcanvas-toggle .fa').removeClass('fa-chevron-right').addClass('fa-chevron-left');
      } else {
        $('.offcanvas-toggle .fa').removeClass('fa-chevron-left').addClass('fa-chevron-right');
      }
    });
    $('[data-toggle="offcanvas"]').click().click();
  } else {
    $('[data-toggle="offcanvas"]').addClass('hidden');
  }
}

function setupBacklinks() {
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
}

function getURLParam(key,target) {
    var values = [];
    if (!target) {
        target = location.href;
    }

	key = key.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
    
    var pattern = key + '=([^&#]+)';
    var o_reg = new RegExp(pattern,'ig');
	while (true) {
		var matches = o_reg.exec(target);
		if (matches && matches[1]) {
			values.push(matches[1]);
		}
		else {
			break;
		}
	}
    if (!values.length) {
         return 'null';   
    }
    else {
        return values.length == 1 ? values[0] : values;
    }
}

function setupAutocomplete() {
  // Search autocomplete
  $('.autocomplete').each(function(i, op) {
    $(op).autocompleteVufind({
      maxResults: 6,
      cache: false,
      loadingString: VuFind.translate('loading')+'...',
      handler: function(query, cb) {
        var searcher = $( 'input[name=database]' ).val();
        if (!$(op).hasClass('autocomplete') || searcher == 'EDS') {
            cb([]);
            return;
        }
        var currentUrl = decodeURIComponent($(location).attr('search'));
        var filters = $( '.searchFormKeepFilters' ).is(':checked') 
        	? getURLParam('filter[]', currentUrl) 
        	: 'null';
        $.fn.autocompleteVufind.ajax({
          url: VuFind.getPath() + '/AJAX/JSON',
          data: {
            q:query,
            method: 'getACSuggestions',
            filters: filters,
            searcher: searcher,
            type: $(op).closest('.searchForm').find('.searchForm_type').val()
          },
          dataType:'json',
          success: function(json) {
            if (json.status == 'OK'
            	&& (json.data.byAuthor.length > 0
            		|| json.data.byTitle.length > 0
            		|| json.data.bySubject.length > 0
                    || json.data.byLibName.length > 0
                    || json.data.byTown.length > 0
            	)
            ) {
              cb(json.data);
            } else {
              cb([]);
            }
          }
        });
      }
    });
  });
  // Update autocomplete on type change
  $('.searchForm_type').change(function() {
    var $lookfor = $(this).closest('.searchForm').find('.searchForm_lookfor[name]');
    $lookfor.autocompleteVufind('clear cache');
    $lookfor.focus();
  });
}

/**
 * Handle arrow keys to jump to next record
 * @returns {undefined}
 */
function keyboardShortcuts() {
    var $searchform = $('#searchForm_lookfor');
    if ($('.pager').length > 0) {
        $(window).keydown(function(e) {
          if (!$searchform.is(':focus')) {
            var $target = null;
            switch (e.keyCode) {
              case 37: // left arrow key
                $target = $('.pager').find('a.previous');
                if ($target.length > 0) {
                    $target[0].click();
                    return;
                }
                break;
              case 38: // up arrow key
                if (e.ctrlKey) {
                    $target = $('.pager').find('a.backtosearch');
                    if ($target.length > 0) {
                        $target[0].click();
                        return;
                    }
                }
                break;
              case 39: //right arrow key
                $target = $('.pager').find('a.next');
                if ($target.length > 0) {
                    $target[0].click();
                    return;
                }
                break;
              case 40: // down arrow key
                break;
            }
          }
        });
    }
}

jQuery( document ).ready( function( $ ) {
  // Setup search autocomplete
  setupAutocomplete();
  // Setup highlighting of backlinks
  setupBacklinks() ;
  // Off canvas
  setupOffcanvas();
  // Keyboard shortcuts in detail view
  keyboardShortcuts();

  // support "jump menu" dropdown boxes
  $('select.jumpMenu').change(function(){ $(this).parents('form').submit(); });

  // Checkbox select all
  $('.checkbox-select-all').change(function() {
    $(this).closest('form').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $('.checkbox-select-item').change(function() {
    $(this).closest('form').find('.checkbox-select-all').prop('checked', false);
  });

  // handle QR code links
  $('a.qrcodeLink').click(function() {
    if ($(this).hasClass("active")) {
      $(this).html(VuFind.translate('qrcode_show')).removeClass("active");
    } else {
      $(this).html(VuFind.translate('qrcode_hide')).addClass("active");
    }

    var holder = $(this).next('.qrcode');
    if (holder.find('img').length == 0) {
      // We need to insert the QRCode image
      var template = holder.find('.qrCodeImgTag').html();
      holder.html(template);
    }
    holder.toggleClass('hidden');
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
    $.getJSON(VuFind.getPath() + '/AJAX/JSON', {method: 'keepAlive'});
  }

  // Advanced facets
  $('.facetOR').click(function() {
    $(this).closest('.collapse').html('<div class="list-group-item">'+VuFind.translate('loading')+'...</div>');
    window.location.assign($(this).attr('href'));
  });

  $('[name=bulkActionForm]').submit(function() {
    return bulkActionSubmit($(this));
  });
  $('[name=bulkActionForm]').find("[type=submit]").click(function() {
    // Abort requests triggered by the lightbox
    $('#modal .fa-spinner').remove();
    // Remove other clicks
    $(this).closest('form').find('[type="submit"][clicked=true]').attr('clicked', false);
    // Add useful information
    $(this).attr("clicked", "true");
  });

  $('input#requiredByDate, input#last-interest-date').datepicker({
    format: "dd.mm.yyyy",
    weekStart: 1,
    language: "cs"
  });

  $(window).on("scroll touchmove", function () {
//	    $('.top-header').toggleClass('hidden', $(document).scrollTop() <= 50).toggleClass('tiny', $(document).scrollTop() > 50);
//	    $('.top-header').toggleClass('full-width', $(document).scrollTop() > 88);

  }); 
  
  $( '.element-help' ).hover( function() {
	  var html = $( this ).find( '.help-text' ).html();
	  $( this ).popover({
		  trigger: 'manual',
		  content: html,
		  placement: 'top',
		  html: true
	  });
	  $( this ).popover( 'show' );
  }).mouseleave( function() {
	  $( this ).popover( 'hide' );
  });

  $( document ).keydown( function (event) {
    if ((( event.keyCode == 8 ) || ( event.keyCode == 27 ))
        && $( '.modal' ).is( ':visible' )
        && !$( '.modal input, .modal textarea, .modal select' ).is( ':focus' )
    ) {
      $( '.modal' ).modal( 'hide' );
    }
  });

    /* Change language */
	$( 'nav' ).on( 'click', '.change-language', function( event ){
		event.preventDefault();

		var language = $( this ).attr( 'data-lang' );

		if ( event.ctrlKey ){
            document.langForm.setAttribute('target', '_blank');
		}

		changeLanguage(language);
	});

    var changeLanguage = function(language) {
		document.langForm.mylang.value = language;
		document.langForm.submit();
	};

    $('.questionmark-help').on('click', function() {
    dataLayer.push({
      'event': 'action.help',
      'actionContext': {
        'eventCategory': 'help',
        'eventAction': 'click',
        'eventLabel': $(this).attr('data-target'),
        'eventValue': undefined,
        'nonInteraction': false
      }
    });
  });

  $(document).on('click','#pay-button', function() {
    dataLayer.push({
      'event': 'action.account',
      'actionContext': {
        'eventCategory': 'account',
        'eventAction': 'payment',
        'eventLabel': 'fines',
        'eventValue': undefined,
        'nonInteraction': false
      }
    });
  });

  $(document).on('click','input[name=renewSelected],input[name=renewAll]', function() {
    dataLayer.push({
      'event': 'action.account',
      'actionContext': {
        'eventCategory': 'account',
        'eventAction': 'prolongItem',
        'eventLabel': undefined,
        'eventValue': undefined,
        'nonInteraction': false
      }
    });
  });


  $('input[name=placeHold]').on('click', function() {
    dataLayer.push({
      'event': 'action.account',
      'actionContext': {
        'eventCategory': 'account',
        'eventAction': 'order',
        'eventLabel': $('input.hiddenId').val(),
        'eventValue': undefined,
        'nonInteraction': false
      }
    });
  });

  $('.show-next-institutions').click( function () {
    var currentMenu = $(this).siblings('.scrollable-menu');
    var windowsize = $(window).width();
    if (windowsize < 480) {
      if (currentMenu.is(':visible')) {
        currentMenu.slideUp()
      }
      else {
        currentMenu.slideDown()
      }
    }
  });

  /* Initialize questionmark helps when available */
  $( '.questionmark-help + .modal' ).appendTo( 'body' );

  // Feedback modal window
  var feedbackModal = document.getElementById( 'feedback-open' );
  if ( feedbackModal ) {
    feedbackModal.onclick = function () {
      $( '#feedback-modal' ).modal( 'show' );
    }
  }
});

/**
 * Parse url query into object
 *
 * @param {string} queryString - Query
 *
 * @return {Object} Parsed query
 */
function parseQueryString(queryString) {
    if (queryString.charAt(0) === '?') {
        queryString = queryString.substr(1);
    }

    let params = {}, queries, temp, i, l;

    // Split into key/value pairs
    queries = queryString.split('&');

    // Convert the array of strings into an object
    for ( i = 0, l = queries.length; i < l; i++ ) {
        temp = queries[i].split('=');
        params[temp[0]] = temp[1];
    }
    return params;
};

/**
 * Build query string from Object params
 *
 * @param {Object} params
 *
 * @return {string} Url query
 */
function httpBuildQuery(params) {
    return Object.keys(params)
        .map(k => encodeURIComponent(k) + '=' + encodeURIComponent(params[k]))
        .join('&');
};

/**
 * Change parameter in url string
 *
 * @param {string} urlString - Origin url to be changed
 * @param {Object} params - Key-value pair Object.
 *                 Object key represents the param name, Object value represents the new param value
 *
 * @return {string} Origin url with changed parameter value
 */
function changeParamsInUrlString(urlString, params, createParamsIfNotExist = false) {

    let parser = document.createElement('a');
    parser.href = urlString;

    // parser.protocol; // => "http:"
    // parser.host;     // => "example.com:3000"
    // parser.hostname; // => "example.com"
    // parser.port;     // => "3000"
    // parser.pathname; // => "/pathname/"
    // parser.hash;     // => "#hash"
    // parser.search;   // => "?search=test"
    // parser.origin;   // => "http://example.com:3000"

    let searchQueryParams = parseQueryString( parser.search );

    Object.keys(params).forEach((key) => {
      if (! (key in searchQueryParams) && ! createParamsIfNotExist) {
        return;
      }
      searchQueryParams[key] = encodeURIComponent(params[key]);
    });

    return parser.origin + parser.pathname + '?' + httpBuildQuery( searchQueryParams ) + parser.hash;
};

function escapeHTML(unsafeStr) {
    return unsafeStr
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/\'/g, '&#39;'); // '&apos;' is not valid HTML 4
}

/**
 * Creates text input and filters items in list by it's data-search param
 *
 * @param param parameters [itemType: ".item-selector", inputClass:".class", placeholder:"Placeholder text"]
 * @returns self
 */
$.fn.listSearch = function (param) {
    param = {
        itemType: param.itemType !== undefined ? param.itemType : 'li.item',
        inputClass: param.inputClass !== undefined ? param.inputClass : 'search-term',
        placeholder: param.placeholder !== undefined ? param.placeholder : 'Write query'
    };
    let that = $(this);
    this.input = $('<input>').addClass(param.inputClass).prop('placeholder', param.placeholder).on("keyup", function (e) {
        // value of text field, count of results
        let value = $(this).val().toLowerCase(), resultCount = 0;
        that.find(param.itemType).filter(function () {
            if ($(this).data('search').toLowerCase().indexOf(value) > -1) {
                $(this).show();
                resultCount++;
            } else {
                $(this).hide();
                // IC the current elem is active one
                $(this).removeClass('active');
            }
        });

        if (resultCount > 0) {
            // move in the searched list
            let activeEl = that.find(param.itemType + '.active'),
                elList = that.find(param.itemType + ':visible'),
                nextEl;

            if (activeEl.length == 0) {
                // no active item, now select the first visible element as active
                elList.first().addClass('active');
            } else {
                // one of previous keypress created active el, now can move in the list
                if ([13, 38, 40].indexOf(e.keyCode) > -1) {
                    // one of these keys pressed
                    switch (e.keyCode) {
                        case 38: // up
                            nextEl = activeEl.prev(param.itemType + ':visible');
                            if (nextEl.length > 0) {
                                activeEl.removeClass('active');
                                nextEl.addClass('active');
                            }
                            break;
                        case 40: // down
                            nextEl = activeEl.next(param.itemType + ':visible');
                            if (nextEl.length > 0) {
                                activeEl.removeClass('active');
                                nextEl.addClass('active');
                            }
                            break;
                        case 13: // enter
                            // simulate click event
                            activeEl.click();
                            break;
                    }
                }
            }
        }
    });
    $(this).prepend(this.input);

    this.focus = function () {
        this.input.focus();
        return this;
    };

    this.clear = function () {
        this.input.val("");
        $(this).find(param.itemType).each(function () {
            $(this).show();
        });
        return this;
    };

    return this;
};
/**
 * JQuery plugin for bootstrap dropdown search filter.
 * Creates input[text].dropdown-search
 * @param e
 */
$.fn.dropFinder = function (placeholder) {
    let dropdownList = $(this).find('.dropdown-menu').listSearch({
        itemType: 'li',
        inputClass: 'dropdown-search',
        placeholder: placeholder
    });
    $(this).on('shown.bs.dropdown', function () {
        dropdownList.focus();
    });
};
var obalky = obalky || {};
obalky.setCacheUrl = function(cacheUrl) {
	obalky.cacheUrl  = cacheUrl;
	obalky.coverUrl  = obalky.cacheUrl + "/api/cover";
	obalky.tocUrl    = obalky.cacheUrl + "/api/toc/thumbnail";
	obalky.pdfUrl    = obalky.cacheUrl + "/api/toc/pdf";
}
obalky.setCacheUrl("https://cache.obalkyknih.cz");
obalky.linkUrl   = "https://www.obalkyknih.cz/view";
obalky.coverText = "cover";
obalky.tocText   = "table of content";

obalky.queryPart = obalky.href || function(bibinfo) {
  var queryPart = "";
  var sep = "";
  $.each(bibinfo, function (name, value) {
    queryPart += sep + name + "=" + encodeURIComponent(value);
    sep = "&";
  });
  return queryPart;
}

obalky.coverTargetUrl = obalky.coverTargetUrl || function (bibinfo) {
  return obalky.linkUrl + "?" + obalky.queryPart(bibinfo);
}

obalky.pdfTargetUrl = obalky.pdfTargetUrl || function (bibinfo) {
  return obalky.pdfUrl + "?" + obalky.queryPart(bibinfo);
}

obalky.imageIsLoaded = obalky.imageIsLoaded || function (image) {
  return (image.height > 1 && image.width > 1);
}

obalky.fetchImage = obalky.fetchImage || function (element, bibinfo, query, type) {
	    var img = new Image();

	    var multi = encodeURIComponent(JSON.stringify(bibinfo));
	    img.onload = function() {
	      if (obalky.imageIsLoaded(img)) {
	        var href = obalky.coverTargetUrl(bibinfo);
	        var dim = "height='80' width='63'";
	        if (type == "thumbnail") {
	            dim = "height='36' width='27'";
	        }
	        $(element).html("<a href='" + href + "' class='title'><img src='" + img.src + "' alt='" + obalky.coverText + "' " + dim + "></img></a>");
	      }
	    }
	    img.src = obalky.coverUrl + "?multi=" + multi + "&type=" + type  + "&keywords=" + encodeURIComponent(query);
}

obalky.fetchImage = obalky.fetchImageWithoutLinks || function (element, bibinfo, query, type) {
    var img = new Image();

    var multi = encodeURIComponent(JSON.stringify(bibinfo));
    img.onload = function() {
      if (obalky.imageIsLoaded(img)) {
        var href = obalky.coverTargetUrl(bibinfo);
        var dim = "height='80' width='63'";
        if (type == "thumbnail") {
            dim = "height='36' width='27'";
        }
        $("[id=" + $(element).attr('id') + "]").html("<img src='" + img.src + "' alt='" + obalky.coverText + "' " + dim + "></img>");
      }
    }
    img.src = obalky.coverUrl + "?multi=" + multi + "&type=" + type  + "&keywords=" + encodeURIComponent(query);
}

obalky.display_thumbnail = obalky.display_thumbnail || function (element, bibinfo, query, type) {
  type = type || "icon";
  
  $(document).ready(
	  obalky.fetchImage(element, bibinfo, query, type)
  );
  
}

obalky.display_thumbnail_without_links = obalky.display_thumbnail || function (element, bibinfo, query, type) {
	  type = type || "icon";
	  
	  $(document).ready(
		  obalky.fetchImageWithoutLinks(element, bibinfo, query, type)
	  );
	  
	}

obalky.display_cover = obalky.display_cover || function (element, bibinfo, query) {
  var multi = encodeURIComponent(JSON.stringify(bibinfo));
  $(document).ready(function() {
    var img = new Image();
    img.onload = function() {
      if (obalky.imageIsLoaded(img)) {
        var href = obalky.coverTargetUrl(bibinfo);
        $(element).html("<div class='cover_thumbnail'><a href='" + href + "' class='title'><img align='left' src='" + img.src + "' alt='" + obalky.coverText + "'></img></a></div>");
      }
    }
    img.src = obalky.coverUrl + "?multi=" + multi + "&type=medium&keywords=" + encodeURIComponent(query);
  });
  $(document).ready(function() {
    var img = new Image();
    img.onload = function() {
      if (obalky.imageIsLoaded(img)) {
        var href = obalky.pdfTargetUrl(bibinfo);
        $(element).append("<div class='toc_thumbnail'><a href='" + href + "' class='title'><img align='left' src='" + img.src + "' alt='" + obalky.tocText + "'></img></a></div>");
      }
    }
    img.src = obalky.tocUrl + "?multi=" + multi + "&type=medium&keywords=" + encodeURIComponent(query);
  });
}

obalky.display_cover_without_links = function (element, bibinfo, query) {
	  var multi = encodeURIComponent(JSON.stringify(bibinfo));
	  $(document).ready(function() {
	    var img = new Image();
	    img.onload = function() {
	      if (obalky.imageIsLoaded(img)) {
	        var href = obalky.coverTargetUrl(bibinfo);
	        $(element).html("<div class='cover_thumbnail'><img align='left' src='" + img.src + "' alt='" + obalky.coverText + "'></img></div>");	  	  
	      }
	    }
	    img.src = obalky.coverUrl + "?multi=" + multi + "&type=medium&keywords=" + encodeURIComponent(query);
	  });
	  
	  $(document).ready(function() {
	    var img = new Image();
	    img.onload = function() {
	      if (obalky.imageIsLoaded(img)) {
	        var href = obalky.pdfTargetUrl(bibinfo);
	        $(element).append("<div class='toc_thumbnail'><a href='" + href + "' class='title'><img align='left' src='" + img.src + "' alt='" + obalky.tocText + "'></img></a></div>");
	      }
	    }
	    img.src = obalky.tocUrl + "?multi=" + multi + "&type=medium&keywords=" + encodeURIComponent(query);
	  });
	  
	  $(document).ready(function() {
		if ($(element).length > 0) {
			if (!$(element+' div:first-child').hasClass("iconlabel")) {
		    $(element).append("<div align='left' style='padding-left: 0px; width:170px; text-align:center;' class='obalky-knih-link col-md-12'>"+VuFind.translate('Source')+": <a href='"+ obalky.coverTargetUrl(bibinfo) +"' class='title' target='_blank'>Obálky knih</a></div>");
		  }
		}
	  });
}

obalky.display_cover_without_links = function (element, bibinfo, query) {
	  var multi = encodeURIComponent(JSON.stringify(bibinfo));
	  $(document).ready(function() {
	    var img = new Image();
	    img.onload = function() {
	      if (obalky.imageIsLoaded(img)) {
	        var href = obalky.coverTargetUrl(bibinfo);
	        $(element).html("<div class='cover_thumbnail clearfix'><img align='left' src='" + img.src + "' alt='" + obalky.coverText + "'></img></div>");
	      }
	    }
	    img.src = obalky.coverUrl + "?multi=" + multi + "&type=medium&keywords=" + encodeURIComponent(query);
	  });
	  
	  $(document).ready(function() {
	    var img = new Image();
	    img.onload = function() {
	      if (obalky.imageIsLoaded(img)) {
	        var href = obalky.pdfTargetUrl(bibinfo);
	        $(element).append("<div class='toc_thumbnail'><a target='_blank' href='" + href + "' class='title'><img align='left' src='" + img.src + "' alt='" + obalky.tocText + "'></img></a></div>");
	      }
	    }
	    img.src = obalky.tocUrl + "?multi=" + multi + "&type=medium&keywords=" + encodeURIComponent(query);
	  });
	  
	  $(document).ready(function() {
		  setTimeout(function() { 
			  if ($(element).length > 0) {
				if (!$(element+' div:first-child').hasClass("iconlabel")) {
			      $(element).append("<div align='left' style='padding-left: 0px; max-width:170px; text-align:center;' class='obalky-knih-link col-md-12'>"+VuFind.translate('Source')+": <a href='"+ obalky.coverTargetUrl(bibinfo) +"' class='title' target='_blank'>Obálky knih</a></div>");
				}
			  } 
		  }, 2000);
	  });
}

obalky.display_thumbnail_cover_without_links = function (element, bibinfo, query) {
	  var multi = encodeURIComponent(JSON.stringify(bibinfo));
	  $(document).ready(function() {
	    var img = new Image();
	    img.onload = function() {
	      if (obalky.imageIsLoaded(img)) {
	        var href = obalky.coverTargetUrl(bibinfo);
	        $(element).html("<div class='cover_thumbnail'><img align='center' width='100' src='" + img.src + "' alt='" + obalky.coverText + "'></img></div>");	  	  
	      }
	    }
	    img.src = obalky.coverUrl + "?multi=" + multi + "&type=medium&keywords=" + encodeURIComponent(query);
	    if (bibinfo.cover_medium_url) {
	        img.src = bibinfo.cover_medium_url;
	    }
	  });
}

obalky.display_authority_cover = function (element, bibinfo, query) {
    var multi = encodeURIComponent(JSON.stringify(bibinfo));
    $(document).ready(function() {
		var auth_id = bibinfo.auth_id;
		$.getJSON( "/AJAX/JSON?method=getObalkyKnihAuthorityID", {id: auth_id}, function( data ) {
			coverurl = data.data;
			var img = new Image();
			img.onload = function() {
				if (obalky.imageIsLoaded(img)) {
					var href = obalky.coverTargetUrl(bibinfo);
					$(element).html("<div class='cover_thumbnail'><a href = 'http://www.obalkyknih.cz/view_auth?auth_id=" + auth_id + "'><img align='left' src='" + img.src + "' alt='" + obalky.coverText + "'></a></div>");
				}
			}
			img.src = coverurl;

		});
    });
}

obalky.display_authority_thumbnail_cover_without_links = function (element, bibinfo, query) {
	var multi = encodeURIComponent(JSON.stringify(bibinfo));
    $(document).ready(function() {
	  var auth_id = bibinfo.auth_id;
		$.getJSON( "/AJAX/JSON?method=getObalkyKnihAuthorityID", {id: auth_id}, function( data ) {
			coverurl = data.data;
			var img = new Image();
			img.onload = function() {
				if (obalky.imageIsLoaded(img)) {
					var href = obalky.coverTargetUrl(bibinfo);
					$(element).html("<div class='cover_thumbnail'><img align='left' width='65' src='" + img.src + "' alt='" + obalky.coverText + "'></div>");
				}
			}
			img.src = coverurl;

		});
    });
}

obalky.display_authority_results = function (element, bibinfo, query) {
    var multi = encodeURIComponent(JSON.stringify(bibinfo));
    $(document).ready(function() {
      var auth_id = bibinfo.auth_id;
        $.getJSON( "/AJAX/JSON?method=getObalkyKnihAuthorityID", {id: auth_id}, function( data ) {
            coverurl = data.data;
            var img = new Image();
            img.onload = function() {
                if (obalky.imageIsLoaded(img)) {
                    var href = obalky.coverTargetUrl(bibinfo);
                    $(element).html("<div class='cover_thumbnail'><img align='left' width='100' src='" + img.src + "' alt='" + obalky.coverText + "'></div>");
                }
            }
            img.src = coverurl;

        });
    });
}

obalky.display_summary = function (element, bibinfo) {
	var multi = encodeURIComponent(JSON.stringify(bibinfo));
	$(document).ready(function() {
		$.getJSON( "/AJAX/JSON?method=getSummaryObalkyKnih", {bibinfo: bibinfo}, function( data ) {
			$(element).html(data.data);
		});
	});
}

obalky.display_summary_short = function (element, bibinfo) {
	var multi = encodeURIComponent(JSON.stringify(bibinfo));
	$(document).ready(function() {
		$.getJSON( "/AJAX/JSON?method=getSummaryShortObalkyKnih", {bibinfo: bibinfo}, function( data ) {
			$(element).html(data.data);
		});
	});
}

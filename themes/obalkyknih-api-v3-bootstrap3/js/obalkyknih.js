var obalky = obalky || {};
obalky.cacheUrl  = obalky.cacheUrl || "https://cache.obalkyknih.cz";
obalky.coverUrl  = obalky.cacheUrl + "/api/cover";
obalky.tocUrl    = obalky.cacheUrl + "/api/toc/thumbnail";
obalky.pdfUrl    = obalky.cacheUrl + "/api/toc/pdf";
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

obalky.display_thumbnail = obalky.display_thumbnail || function (element, bibinfo, query, type) {
  type = type || "icon";
  
  $(document).ready(
	  obalky.fetchImage(element, bibinfo, query, type)
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

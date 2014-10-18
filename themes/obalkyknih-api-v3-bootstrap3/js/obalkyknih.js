function obalky_display_thumbnail(element, bibinfo, query) {
  $(document).ready(function() {
    var img = new Image();
    img.onload = function(){
      var height = img.height;
      var width = img.width;
      if (height > 1 && width > 1) {
        cover_text = 'cover';
        $(element).html("<a href='" + img.src + "' class='title'><img src='" + img.src + "' alt='" + cover_text + "' height='80' width='63'></img></a>");
      }
    }
    var multi = encodeURIComponent(JSON.stringify(bibinfo));
    img.src = "http://cache.obalkyknih.cz/api/cover?multi=" + multi + "&type=icon";
  });
}

function obalky_display_cover(element, bibinfo, query) {
  $(document).ready(function() {
    var multi = encodeURIComponent(JSON.stringify(bibinfo));
    var img = new Image();
    img.onload = function(){
      var height = img.height;
      var width = img.width;
      if (height > 1 && width > 1) {
        cover_text = 'cover';
        var href = "https://www.obalkyknih.cz/view?multi=" + multi;
        $(element).html("<div class='cover_thumbnail'><a href='" + href + "' class='title'><img align='left' src='" + img.src + "' alt='" + cover_text + "'></img></a></div>");
      }
    }
    img.src = "http://cache.obalkyknih.cz/api/cover?multi=" + multi + "&type=medium";
  });
  $(document).ready(function() {
    var img = new Image();
    img.onload = function(){
      var height = img.height;
      var width = img.width;
      if (height > 1 && width > 1) {
        var cover_text = 'cover';
        var href = img.src.replace('/toc/thumbnail?', '/toc/pdf?');
        $(element).append("<div class='toc_thumbnail'><a href='" + href + "' class='title'><img align='left' src='" + img.src + "' alt='" + cover_text + "'></img></a></div>");
      }
    }
    var multi = encodeURIComponent(JSON.stringify(bibinfo));
    img.src = "http://cache.obalkyknih.cz/api/toc/thumbnail?multi=" + multi + "&type=medium";
  });
}

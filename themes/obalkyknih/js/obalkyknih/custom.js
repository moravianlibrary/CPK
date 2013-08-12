
function obalky_display_thumbnail(element, bibinfo) {
  href = bibinfo["cover_thumbnail_url"];
  if (href != undefined) {
    cover_text = 'FIXME';
    $(element).html("<a href='" + bibinfo["backlink_url"] + "'><img align='left' src='" + bibinfo["cover_medium_url"] + "' alt='" + cover_text + "' height='80' width='63'></img></a>");
  }
}

function obalky_display_cover(element, bibinfo) {
  var cover_text = 'FIXME';
  var href = bibinfo["cover_medium_url"];
  var backlink = bibinfo["backlink_url"];
  if (href == undefined) {
    href = bibinfo["toc_thumbnail_url"];
    backlink = bibinfo["toc_pdf_url"];
  }
  if (href != undefined) {
    $(element).html("<div class='cover_thumbnail'> <a href='" + backlink + "'><img align='left' src='" + href + "' alt='" + cover_text + "'></img></a></div>");
  }
  toc_url = bibinfo["toc_pdf_url"];
  if (toc_url != undefined) {
    toc_thumbnail_url = bibinfo["toc_thumbnail_url"];
    $(element).append("<div class='toc_thumbnail'> <a href='" + toc_url + "'><img align='left' src='" + toc_thumbnail_url + "' alt='" + cover_text + "'></img></a></div>");
  }
}

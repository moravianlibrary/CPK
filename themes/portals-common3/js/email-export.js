function getAllByClass(classname, node) {

    if (!document.getElementsByClassName) {
        if (!node) {
            node =  document.body;
        }

        var a = [],
            re = new RegExp('\\b' + classname + '\\b'),
            els = node.getElementsByTagName("*");

        for (var i = 0, j = els.length; i < j; i++) {
            if (re.test(els[i].className)) {
                a.push(els[i]);
            }
        }
    } else {
        return document.getElementsByClassName(classname);
    }

    return a;
}

function processEmailSearch(url) {
  var arrayID = getAllByClass('recordId'); 
  var ids = new Array(arrayID.length);
  for (i = 0; i < arrayID.length; i++) {
    ids[i] = 'exportID[]=' + arrayID[i].innerHTML;
  }
  var idString = url.href + '?' + ids.join('&');
  window.location.replace(idString);
}

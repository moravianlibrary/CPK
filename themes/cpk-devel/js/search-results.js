function showNextInstitutions(obj) {
    var anchors = obj.parentNode.parentNode.getElementsByTagName('a');
    
    $(anchors).each(function(key, val) {val.removeAttribute('hidden')});
    
    obj.remove();
}
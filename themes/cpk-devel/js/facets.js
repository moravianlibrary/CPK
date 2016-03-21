/*global htmlEncode, VuFind */
function buildFacetNodes(data, currentPath, allowExclude, excludeTitle, counts)
{
  var json = [];

  $(data).each(function() {
    var html = '';
    
    var lastFacetInString = this.exclude.split( '=' ).pop();
    var facetName = lastFacetInString.split( '%3A' ).shift().substring(1);
    var facetFilter = facetName + ':"' + this.value + '"';
    if (!this.isApplied && counts) {
      html = "<span class='badge' style='float: right'>" + this.count.toString().replace(/\B(?=(\d{3})+\b)/g, VuFind.translate("number_thousands_separator"));
      if (allowExclude) {
        var excludeURL = currentPath + this.exclude;
        excludeURL.replace("'", "\\'");
        // Just to be safe
        html += " <a href='" + facetFilter + "' title='" + htmlEncode(excludeTitle) + "'><i class='fa fa-times'></i></a>";
      }
      html += '</span>';
    }

    var institutionCategory = facetFilter.split('/')[1];
    
    var url = currentPath + this.href;
    // Just to be safe
    url.replace("'", "\\'");
    html += "<span data-facet='" + facetFilter + "' class='main" + (this.isApplied ? " applied" : "") 
    + (facetName != "institution" ? " facet-filter" : "") 
    + (
    	(
    		( (facetName == 'institution') && (institutionCategory == "Library") && (this.level == "2") )
    		||  ( (facetName == 'institution') && (institutionCategory == 'Others') && (this.level == "1") )
    	) 
    	? " institution-facet-filter" 
    	: ""
    ) 
    + "' title='" + htmlEncode(this.displayText) + "'>";
    if (this.operator == 'OR') {
      if (this.isApplied) {
        html += '<i class="fa fa-check-square-o"></i>';
      } else {
        html += '<i class="fa fa-square-o"></i>';
      }
    } else if (this.isApplied) {
      html += '<i class="fa fa-check pull-right"></i>';
    }
    html += ' ' + this.displayText;
    html += '</span>';

    var children = null;
    if (typeof this.children !== 'undefined' && this.children.length > 0) {
      children = buildFacetNodes(this.children, currentPath, allowExclude, excludeTitle, counts);
    }
    json.push({
      'text': html,
      'children': children,
      'applied': this.isApplied,
      'state': {
        'opened': this.hasAppliedChildren
      },
      'li_attr': this.isApplied ? { 'class': 'active' } : {}
    });
  });

  return json;
}

function initFacetTree(treeNode, inSidebar)
{
  var loaded = treeNode.data('loaded');
  if (loaded) {
    return;
  }
  treeNode.data('loaded', true);

  var facet = treeNode.data('facet');
  var operator = treeNode.data('operator');
  var currentPath = treeNode.data('path');
  var allowExclude = treeNode.data('exclude');
  var excludeTitle = treeNode.data('exclude-title');
  var sort = treeNode.data('sort');
  var query = window.location.href.split('?')[1];

  if (inSidebar) {
    treeNode.prepend('<li class="list-group-item"><i class="fa fa-spinner fa-spin"></i></li>');
  } else {
    treeNode.prepend('<div><i class="fa fa-spinner fa-spin"></i><div>');
  }
  $.getJSON(VuFind.getPath() + '/AJAX/JSON?' + query,
    {
      method: "getFacetData",
      facetName: facet,
      facetSort: sort,
      facetOperator: operator
    },
    function(response, textStatus) {
      if (response.status == "OK") {
        var results = buildFacetNodes(response.data, currentPath, allowExclude, excludeTitle, inSidebar);
        treeNode.find('.fa-spinner').parent().remove();
        if (inSidebar) {
          treeNode.on('loaded.jstree open_node.jstree', function (e, data) {
            treeNode.find('ul.jstree-container-ul > li.jstree-node').addClass('list-group-item');
          });
        }
        treeNode.jstree({
          'core': {
            'data': results
          }
        });
      }
    }
  );
}

/*
 * http://www.obalkyknih.cz
 *
 * API Functions
 *
 * (c)2009 Martin Sarfy <martin@sarfy.cz>
 */

var obalky = obalky || {};

obalky.protocol = 
	(document.location.protocol == 'https:') ? 'https://':'http://';
obalky.url = obalky.protocol+"www.obalkyknih.cz";

obalky.books = obalky.books || [];
obalky.version = "0.2.0";
obalky.id = 0;
obalky.msie = navigator.userAgent.indexOf("MSIE") != -1 ? 1 : 0;

obalky.callback = obalky.callback || function (books) {
	for(var i=0;i<books.length;i++) {
		for(var j=0;j<books[i]["callbacks"].length;j++) {
			callback = books[i]["callbacks"][j];
			var element = document.getElementById(callback["id"]);
			var function_pointer = eval(callback["name"]);
			if(function_pointer) function_pointer(element,books[i]);
		}
	}
};

obalky.download = obalky.download || function (books) {
	var obalky_json = "obalky_json_"+obalky.id++;
	var jsonScript = document.getElementById(obalky_json);
	if (jsonScript) jsonScript.parentNode.removeChild(jsonScript);
	var scriptElement = document.createElement("script");
	scriptElement.setAttribute("id", obalky_json);
	scriptElement.setAttribute("type", "text/javascript");
	scriptElement.setAttribute("src", obalky.url+"/api/books?books="+
		encodeURIComponent(JSON.stringify(books)));
	document.documentElement.firstChild.appendChild(scriptElement);
};

obalky.findFirstNodeByClass = function (startNode, className) {
	if(!(startNode.nodeType === 1)) return null; // skip non-elements
	if(startNode.className == className) return startNode;
	var childs = startNode.childNodes;
	for(var i=0;i<childs.length;i++) {
		var found = obalky.findFirstNodeByClass(childs[i], className);
		if(found) return found;
	}
	return null;
};

obalky.findNodesByClass = function (startNode, className) {
	var nodes = (startNode.className == className) ? [ startNode ] : [];
	var childs = startNode.childNodes;
	for(var i=0;i<childs.length;i++)
		nodes = nodes.concat(obalky.findNodesByClass(childs[i], className));
	return nodes;
};
obalky.getValue = function (startNode, className) {
	var el = obalky.findFirstNodeByClass(startNode, className);
	return el ? (el.textContent ? el.textContent : el.innerHTML ) : undefined;
};
obalky.onload = function() {
	var id = 0;
	var book_els = obalky.findNodesByClass(document.body,"obalky_book");
	var books = [];
	for(var i=0;i<book_els.length;i++) {
		var book = book_els[i];
		// nacti permalink
		var permalink = obalky.getValue(book,"obalky_permalink");
	    var el = obalky.findFirstNodeByClass(book, "obalky_permalink");
		if(!permalink) continue;	

		var info = obalky.findFirstNodeByClass(book,"obalky_bibinfo");
		if(!info) continue;

		// nacti ostatni...
		var bibinfo = {};
		bibinfo["isbn"] = obalky.getValue(info,"obalky_isbn");

		// zkontroluj povinna metadata?

		var callback_els = obalky.findNodesByClass(book,"obalky_callback");
		var calls = [];
		for(var j=0;j<callback_els.length;j++) {
			var callback = callback_els[j];
			callback.id = callback.id ? callback.id : "obalky_callback_"+(++id);
			calls.push(	{ "name": callback.getAttribute("name"), 
						  "id": callback.id });
		}
		books.push( { "permalink": permalink, "bibinfo": bibinfo, 
		              "callbacks": calls } );
		if(obalky.msie || books.length > 10) {
			obalky.download(books);
			books = []; 
		}
	}
	if(!obalky.msie) obalky.download(books);
};
obalky.process = function(callback_name, element_id, permalink, bibinfo) {
	var books = [ { "permalink" : permalink, "bibinfo": bibinfo, 
			"callbacks": [ { "name": callback_name, "id": element_id } ] } ];
	obalky.download(books);
}
/*obalky.previous_onload = window.onload; // IE way...
window.onload = function() {
	if(obalky.previous_onload) obalky.previous_onload();
	obalky.onload();
	if(obalky_custom_onload) obalky_custom_onload();
};*/
/*document.addEventListener("load", function(event) { obalky.onload(); }, false);*/


/*
   if (document.addEventListener) {
       document.addEventListener("DOMContentLoaded", obalky.onload, false);
   } else if (window.attachEvent) {
		 window.attachEvent("onload", obalky.onload); 
	}
*/


/* ------------------------------------------------------------------------- */
/* http://www.JSON.org/json2.js (minified)                                   */
if(!this.JSON){this.JSON={};}
(function(){function f(n){return n<10?'0'+n:n;}
if(typeof Date.prototype.toJSON!=='function'){Date.prototype.toJSON=function(key){return isFinite(this.valueOf())?this.getUTCFullYear()+'-'+
f(this.getUTCMonth()+1)+'-'+
f(this.getUTCDate())+'T'+
f(this.getUTCHours())+':'+
f(this.getUTCMinutes())+':'+
f(this.getUTCSeconds())+'Z':null;};String.prototype.toJSON=Number.prototype.toJSON=Boolean.prototype.toJSON=function(key){return this.valueOf();};}
var cx=/[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,escapable=/[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,gap,indent,meta={'\b':'\\b','\t':'\\t','\n':'\\n','\f':'\\f','\r':'\\r','"':'\\"','\\':'\\\\'},rep;function quote(string){escapable.lastIndex=0;return escapable.test(string)?'"'+string.replace(escapable,function(a){var c=meta[a];return typeof c==='string'?c:'\\u'+('0000'+a.charCodeAt(0).toString(16)).slice(-4);})+'"':'"'+string+'"';}
function str(key,holder){var i,k,v,length,mind=gap,partial,value=holder[key];if(value&&typeof value==='object'&&typeof value.toJSON==='function'){value=value.toJSON(key);}
if(typeof rep==='function'){value=rep.call(holder,key,value);}
switch(typeof value){case'string':return quote(value);case'number':return isFinite(value)?String(value):'null';case'boolean':case'null':return String(value);case'object':if(!value){return'null';}
gap+=indent;partial=[];if(Object.prototype.toString.apply(value)==='[object Array]'){length=value.length;for(i=0;i<length;i+=1){partial[i]=str(i,value)||'null';}
v=partial.length===0?'[]':gap?'[\n'+gap+
partial.join(',\n'+gap)+'\n'+
mind+']':'['+partial.join(',')+']';gap=mind;return v;}
if(rep&&typeof rep==='object'){length=rep.length;for(i=0;i<length;i+=1){k=rep[i];if(typeof k==='string'){v=str(k,value);if(v){partial.push(quote(k)+(gap?': ':':')+v);}}}}else{for(k in value){if(Object.hasOwnProperty.call(value,k)){v=str(k,value);if(v){partial.push(quote(k)+(gap?': ':':')+v);}}}}
v=partial.length===0?'{}':gap?'{\n'+gap+partial.join(',\n'+gap)+'\n'+
mind+'}':'{'+partial.join(',')+'}';gap=mind;return v;}}
if(typeof JSON.stringify!=='function'){JSON.stringify=function(value,replacer,space){var i;gap='';indent='';if(typeof space==='number'){for(i=0;i<space;i+=1){indent+=' ';}}else if(typeof space==='string'){indent=space;}
rep=replacer;if(replacer&&typeof replacer!=='function'&&(typeof replacer!=='object'||typeof replacer.length!=='number')){throw new Error('JSON.stringify');}
return str('',{'':value});};}
if(typeof JSON.parse!=='function'){JSON.parse=function(text,reviver){var j;function walk(holder,key){var k,v,value=holder[key];if(value&&typeof value==='object'){for(k in value){if(Object.hasOwnProperty.call(value,k)){v=walk(value,k);if(v!==undefined){value[k]=v;}else{delete value[k];}}}}
return reviver.call(holder,key,value);}
cx.lastIndex=0;if(cx.test(text)){text=text.replace(cx,function(a){return'\\u'+
('0000'+a.charCodeAt(0).toString(16)).slice(-4);});}
if(/^[\],:{}\s]*$/.test(text.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,'@').replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,']').replace(/(?:^|:|,)(?:\s*\[)+/g,''))){j=eval('('+text+')');return typeof reviver==='function'?walk({'':j},''):j;}
throw new SyntaxError('JSON.parse');};}}());


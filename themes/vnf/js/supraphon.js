/**
 * loads Supraphon label of image, if exists
 * @param element     parent element
 * @param mediumUrl   URL of medium size image
 * @param mediumCapt  caption of medium image
 * @param largeUrl    URL of large size image
 * @param defaultUrl  URL to display if image is not found
 * @parem defaultCapt caption of missing image
 */
function addSupraphonCover(element, mediumUrl, mediumCapt, largeUrl, defaultUrl, defaultCapt) {
	
	var img = new Image();
	img.class = "recordcover";
	
	img.onerror = function (evt){
		var missing = new Image();
		missing.alt = defaultCapt;
		missing.src = defaultUrl;
		$(element).append(missing);
	};
	img.onload = function (evt){
    	var largeLink = $("<a>")
    	  .attr("href", largeUrl)
    	  .append(img);
        $(element).append(largeLink);
	};
	
	img.alt = mediumCapt;
	img.src = mediumUrl;

}

function addSupraphonCoverSmall(element, mediumUrl, mediumCapt, defaultUrl, defaultCapt) {
	
	var img = new Image();
	img.class = "recordcover";
	
	img.onerror = function (evt){
		var missing = new Image();
		missing.alt = defaultCapt;
		missing.src = defaultUrl;
		$(element).append(missing);
	};
	img.onload = function (evt){
        $(element).append(img);
	};
	
	img.alt = mediumCapt;
	img.src = mediumUrl;
	

}
/**
 * Class Favorite for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').value('Favorite', Favorite);

    Favorite.$inject = [ '$log' ];

    function Favorite($log) {

	// Private
	var vars = {
	    title : {
		link : undefined,
		value : undefined
	    },
	    author : {
		link : undefined,
		value : undefined
	    },
	    published : undefined,
	    format : {
		iconClass : undefined,
		value : undefined
	    },
	    image : undefined,
	    created : (new Date()).getTime()
	};

	// Public
	var vm = this;

	vm.parseCurrentRecord = function() {

	    var tablePointer = $('table[summary]');
	    var authorPointer = tablePointer.find('tbody tr td[property=author] a');
	    var formatPointer = tablePointer.find('tbody tr td div.iconlabel');

	    // Current pathname should be the right link
	    vm.titleLink(location.pathname);
	    vm.title(parseTitle());

	    vm.authorLink(parseAuthorLink());
	    vm.author(parseAuthor());

	    vm.formatIconClass(parseFormatIconClass());
	    vm.format(parseFormat());

	    vm.published(parsePublished());

	    vm.image(parseImage());

	    return vm; // Respect fluent API

	    function parseTitle() {
		var expectedSiblingHeader = tablePointer.siblings('h3');

		if (expectedSiblingHeader.length)
		    return expectedSiblingHeader.text();
		else
		    $log.error('Parsing record title failed!');
	    }

	    function parseAuthorLink() {
		var link = authorPointer.prop('href');

		if (typeof link === "string")
		    return link;
		else
		    $log.error('Parsing author\'s link failed!');
	    }

	    function parseAuthor() {
		var author = authorPointer.text();

		if (typeof author === "string")
		    return author;
		else
		    $log.error('Parsing author\'s name failed!');
	    }

	    function parseFormatIconClass() {
		var expectedIcon = formatPointer.children('i');

		if (expectedIcon.length)
		    return expectedIcon.attr('class');
		else
		    $log.error('Parsing format icon class failed!');
	    }

	    function parseFormat() {
		var expectedSpan = formatPointer.children('span');

		if (expectedSpan.length)
		    return expectedSpan.text();
		else
		    $log.error('Parsing record format failed!');
	    }

	    function parsePublished() {
		var expectedSpan = tablePointer.find('tbody tr td span[property=publicationDate]');

		if (expectedSpan.length)
		    return expectedSpan.text();
		else
		    $log.error('Parsing publication year failed!');
	    }

	    function parseImage() {
		var expectedParentSiblingSmallDivision = tablePointer.parent().siblings('div.col-sm-3');

		if (expectedParentSiblingSmallDivision.length) {
		    var expectedImg = expectedParentSiblingSmallDivision.find('img');

		    if (expectedImg.length)
			return expectedImg.attr('src');
		    else
			$log.error('Parsing record image source failed!');
		} else
		    // I think this might be appreciated in the future ..
		    $log.error('Parsing record image\'s parent division failed!');
	    }
	}
	
	vm.toObject = function() {
	    return vars;
	}

	// Override native method toString

	vm.toString = function() {
	    return JSON.stringify(vars);
	};

	// Now are here only setters/getters ..

	vm.titleLink = function(titleLink) {

	    if (typeof titleLink === "undefined") {
		return vars.title.link;
	    } else {
		vars.title.link = titleLink;
	    }
	}

	vm.title = function(title) {

	    if (typeof title === "undefined") {
		return vars.title.value;
	    } else {
		vars.title.value = title;
	    }
	}

	vm.authorLink = function(authorLink) {

	    if (typeof authorLink === "undefined") {
		return vars.author.link;
	    } else {
		vars.author.link = authorLink;
	    }
	}

	vm.author = function(author) {

	    if (typeof author === "undefined") {
		return vars.author.value;
	    } else {
		vars.author.value = author;
	    }
	}

	vm.published = function(published) {

	    if (typeof published === "undefined") {
		return vars.published;
	    } else {
		vars.published = published;
	    }
	}

	vm.formatIconClass = function(formatIconClass) {

	    if (typeof formatIconClass === "undefined") {
		return vars.format.iconClass;
	    } else {
		vars.format.iconClass = formatIconClass;
	    }
	}

	vm.format = function(format) {

	    if (typeof format === "undefined") {
		return vars.format.value;
	    } else {
		vars.format.value = format;
	    }
	}

	vm.image = function(image) {

	    if (typeof image === "undefined") {
		return vars.image;
	    } else {
		vars.image = image;
	    }
	}

	vm.created = function() {
	    return vars.created;
	}
    }
})();
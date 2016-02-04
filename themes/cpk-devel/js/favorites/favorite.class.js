/**
 * Class Favorite for favorites AngularJS app.
 * 
 * Because this Favorite class is implemented as an value, we cannot inject
 * anything, thus it will be logging using native console.error() if any occurs ..
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').value('Favorite', Favorite);

    function Favorite() {

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
		    console.error('Parsing record title failed!');
	    }

	    function parseAuthorLink() {
		var link = authorPointer.prop('href');

		if (typeof link === "string")
		    return link;
		else
		    console.error('Parsing author\'s link failed!');
	    }

	    function parseAuthor() {
		var author = authorPointer.text();

		if (typeof author === "string")
		    return author;

		console.error('Parsing author\'s name failed!');
	    }

	    function parseFormatIconClass() {
		var expectedIcon = formatPointer.children('i');

		if (expectedIcon.length)
		    return expectedIcon.attr('class');

		console.error('Parsing format icon class failed!');
	    }

	    function parseFormat() {
		var expectedSpan = formatPointer.children('span');

		if (expectedSpan.length)
		    return expectedSpan.attr('data-orig');

		console.error('Parsing record format failed!');
	    }

	    function parsePublished() {
		var expectedSpan = tablePointer.find('tbody tr td span[property=publicationDate]');

		if (expectedSpan.length)
		    return expectedSpan.text();

		console.error('Parsing publication year failed!');
	    }

	    function parseImage() {
		var expectedParentSiblingSmallDivision = tablePointer.parent().siblings('div.col-sm-3');

		if (expectedParentSiblingSmallDivision.length) {
		    var expectedImg = expectedParentSiblingSmallDivision.find('img');

		    if (expectedImg.length)
			return expectedImg.attr('src');

		    console.error('Parsing record image source failed!');
		} else
		    // I think this might be appreciated in the future ..
		    console.error('Parsing record image\'s parent division failed!');
	    }
	}

	vm.fromObject = function(obj) {

	    if (typeof obj !== "object") {
		console.error('Trying to create Favorite from object, but no object passed');

	    } else if (! obj.hasOwnProperty('created')) {
		console.error('Missing timestamp of the object!');

	    } else {
		vars = obj;
	    }

	    return vm;
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
	    }

	    vars.title.link = titleLink;
	    return vm;
	}

	vm.title = function(title) {

	    if (typeof title === "undefined") {
		return vars.title.value;
	    }

	    vars.title.value = title;
	    return vm;
	}

	vm.authorLink = function(authorLink) {

	    if (typeof authorLink === "undefined") {
		return vars.author.link;
	    }

	    vars.author.link = authorLink;
	    return vm;
	}

	vm.author = function(author) {

	    if (typeof author === "undefined") {
		return vars.author.value;
	    }

	    vars.author.value = author;
	    return vm;
	}

	vm.published = function(published) {

	    if (typeof published === "undefined") {
		return vars.published;
	    }

	    vars.published = published;
	    return vm;
	}

	vm.formatIconClass = function(formatIconClass) {

	    if (typeof formatIconClass === "undefined") {
		return vars.format.iconClass;
	    }

	    vars.format.iconClass = formatIconClass;
	    return vm;
	}

	vm.format = function(format) {

	    if (typeof format === "undefined") {
		return vars.format.value;
	    }

	    vars.format.value = format;
	    return vm;
	}

	vm.image = function(image) {

	    if (typeof image === "undefined") {
		return vars.image;
	    }

	    vars.image = image;
	    return vm;
	}

	vm.created = function() {
	    return vars.created;
	}
    }
})();
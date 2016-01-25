/**
 * Editor Controller for favorites AngularJS app.
 * 
 * @author Jiří Kozlovský
 */
(function() {

    angular.module('favorites').controller('EditorController', EditorController);
    
    function EditorController() {

	this.pagesInterval = {
	    "from" : undefined,
	    "to" : undefined
	};

	this.pagesTotal = undefined;

	this.listId = 999;

	this.listTitle = "Your Favorites"; // TODO change it to real title or
	// at least translate

	this.description = "";

	this.editModeActive = true;

	this.listEmpty = false;

	this.editList = function(id) {
	    alert("editing list " + id)
	};

	this.deleteList = function(id) {
	    alert("deleting list " + id)
	};

	this.submitBulk = function() {
	};
    }
})();
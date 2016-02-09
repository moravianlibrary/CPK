/**
 * 
 * Basic concept:
 * 
 * This angularJS based app is written in order to let the not logged in user
 * have access to favorites. To be capable of creating them, deleting them &
 * sharing them.
 * 
 * What do we need to achieve this goal and what problems are we facing in this
 * implementation?
 * 
 * First we need to realize that some not logged in user should be able to save,
 * delete & share all his favorites. If we have stored his favorites on server,
 * we would have to deal with the problem of "When we should delete the stored
 * favorites ?" By a cron job? That's not the way appaerently .. So that's why
 * we are going to use user's browser's local storage. That's why we have to use
 * javascript to achieve this goal. It also seems to be an "ecological" solution
 * by preventing some amount of data to flow through the internet.
 * 
 * Also, it's an complex task to complete, so I've decided to use AngularJS'
 * MVC.
 * 
 * 1) Privacy:
 * 
 * Not logged user should retain his privacy so that's why need to delete all
 * the favorites as soon, as the browser closes. This can be done using
 * sessionStorage. We are going to use this nice localForage driver:
 * https://github.com/thgreasi/localForage-sessionStorageWrapper .. see also
 * directory js/vendor/ with "localforage-*.js" wildcard match
 * 
 * 2) Transferring favorites from not logged in user to logged in user:
 * 
 * We will have to create some transfer model to let user's browser's content
 * from session storage tranfer to server's MySQL over an PHP middleware. ->
 * TODO: What's the best way to complete this task?
 * 
 * 3) Creating favorites
 * 
 * We should replace all the current links within all the phtml templates being
 * pointed to an PHP controller handling favorites for logged in users with
 * javascript solution (assigning handlers & controllers from this app to them
 * instead of the links).
 * 
 * 4) Deleting favorites
 * 
 * This is an easy task - there'll be a simple delete button as is now, but is
 * about to be handled by JS, not PHP.
 * 
 * 5) Notifying user about having favorites & not being logged in yet
 * 
 * User should know the risks of having his favorites within an "offline"
 * account, I mean to let him know about the fact that he could lose those
 * favorites very easily, so he should log in in order to retain his favorites
 * on the server, not the browser.
 * 
 * Note that the Favorites app is being written with respect to Angular Style
 * Guide you can look at here:
 * https://github.com/johnpapa/angular-styleguide#angular-style-guide
 * 
 * 6) All other functionalities should be for logged in users only
 * 
 * TODO list:
 * 
 * Implement transferring favorites from user's sessionStorage to the server
 * after login.
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('favorites', []);
})();
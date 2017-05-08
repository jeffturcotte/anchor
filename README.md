=========================================
= Anchor // a routing library for PHP 5 =
=========================================

[![Build Status](https://travis-ci.org/imarc/anchor.png?branch=master)](https://travis-ci.org/imarc/anchor)

The following is a short example on how to use Anchor. Once you've gone
through this, there is additional PHPDoc documentation in the class.


1. Configuring Anchor for your Controllers

The simplest way to use controller classes with Anchor is to pass a folder
that contains the controllers. Anchor will attempt to load your controller
classes by appending .php to the class name.

Anchor::setControllerPath('/path/to/controller/dir');

If you are using PHP 5.2 namespacing where _ characters are converted to
folder separators (e.g. slashes), then be sure to add:

Anchor::setLegacyNamespacing();

If you want to load you controller classes on your own, you need to authorize
Anchor to use them. This helps prevent vulnerabilities based on user input.
All controller classes, or one of the classes they inherit from, must be
authorized in order for Anchor to use them. The following authorizes all
classes that inherit from BaseController.

Anchor::authorize('BaseController');


2. Adding Routes

Next, you need to define the routes that Anchor will handle. Anchor::add()
accepts two parameters, the URL to route and the callback to pass it to.

Anchor::add('/', 'Home::main');

While Home::main looks like a static method, all controller methods must
be public instance methods, otherwise Anchor will not use them. This is another
security feature.

You pull values out of URLs by using one of the four symbols, followed by
a request variable name. The four symbols are the following single character:

! - matches letters, numbers and underscores
: - matches anything but a slash
% - matches numbers
@ - matches letters
* - matches anything

Here is an example of pulling a numeric id and string slug out of a URL:

Anchor::add('/blog/%id-:slug', 'Blogs::view');

These values will be pushed into the $_GET and $_REQUEST superglobals with
the names you define.

There are three param names that are reserved by the class for use in
determining what controller should be run when the controller includes
wildcards. There are "class", "method" and "namespace". For instance:

Anchor::add('/!class/!method', '*::*');

Will attempt to run the Blog::list() controller method if the URL is /blog/list,
but will attempt to run About::team() if the URL is /about/team.

There is one special route, the 404 route, which is called if no other routes
match.

Anchor::add('404', 'Home::notFound');


3. Running Anchor

Once Anchor is configured and the routes are added, you run it by calling:

Anchor::run();


4. Linking

You can generate the link to a controller by passing it, with any required
data to the link method:

Anchor::link('Users::view id slug', 5, 'john_smith');

This will find the route for Users::view that has the params id and slug in
it and create a link. If no routes exist with those params, they will be
added as query string params.


5. Full Example

// .htaccess file in root of website

RewriteEngine    On
RewriteBase      /
RewriteCond      %{REQUEST_FILENAME}    !-f
RewriteRule      ^(.*)$                 bootstrap.php [L,QSA]


// bootstrap.php

include $_SERVER['DOCUMENT_ROOT'] . '/../init.php';

Anchor::setControllerPath('/path/to/controller/dir');

Anchor::add('/!class/!method/%id-:slug', '*::*');
Anchor::add('/!class/!method', '*::*');
Anchor::add('/', 'Home::main');
Anchor::add('404', 'Home::notFound');

Anchor::run();


// Controller class in /path/to/controller/dir/Users.php

class Users {
	public function view($data) {
		$id = $_GET['id'];
		// do stuff here and output response
	}
}

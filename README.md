# NNTP Forum - A modern frontend to newsgroups

Newsgroups often have a somewhat aged appearance. This often makes them unattractive for younger people that only know forums or newer stuff. The NNTP Forum tries to give newsgroups a more modern appearance to make them more usable for young people.

## Features

- Presents a newsgroup server as a forum
	- First displays all newsgroups on a server
	- Then all topics in a newsgoup
	- And all messages in a topic
- Post new topics and messages
- Supports attachments
- The tree structure of the messages is preserved
	- You can answer to a specific message
	- You can hide all answers to a specific message (collapse a topic branch)
- Markdown is used to display a message as HTML
- Automatically collapses large quote blocks (e.g. the quote of the previous message)
- Highlights unread messages
	- The unread tracker data is stored on the server, one small file per user
	- The tracker data does not grow over time but only with the number of users (only the 50 newest messages in each group are tracked)
- Provides freely configurable newsfeeds for the newsgroups

This is the basic stuff. Right now the forum is optimized for the environment of my university. Therefore the authorization of users is handled in an unusual way:

- Uses HTTP authorization to get user credentials: The authorization method can be feely configured in the webserver, e.g. Apache. Right now we use an LDAP lookup. The downside of this is that the user is always asked for credentials. Guest access would need to be whitelisted in the virtual host config with a proper guest user. This guest user credentials can then be used by the NNTP forum to access the newsgroups.
- When posting a message the username is translated to a display name with an LDAP lookup.

Ok, now to the technical stuff:

- Written in PHP 5.3
- Messages are parsed per line so almost no memory is required (important for large attachments)
- Caching is used extensively to reduce load on the NNTP server
- The frontend does _not_ copy the newsgroup content to a new database
- Almost no new data is stored in the frontend (the exceptions are the unread tracker data and the cache)
- Supports encrypted NNTP connections
- URLs are rewritten to nice and short URLs using Apaches mod_rewrite

## Requirements

- PHP 5.3 or newer
- Apache2 with `mod_rewrite` enabled

The frontend is currently only tested with INN 2.5.2 (InterNetNews NNRP server).

## Download and installation

1. Download and extract or checkout the project here at GitHub.com
2. The file `site.conf` is a template for an Apache2 virtual host. However you need to customize it for your setup:
	- Replace the default path `/srv/sites/news.example.com` with the path you put the files to.
	- Configure the authentication backend by setting the `AuthBasicProvider` directive. You can use whatever auth backend you want but by default the NNTP forum uses the supplied HTTP authentication for the NNTP connection to the newsgroup. So it's best to use the same authentication backend for the frontend you already use for your NNTP server. A list of Apaches authentication providers can be found in the Apache2 documentation under [Authentication, Authorization and Access Control][i1].
	  
	  If you want a public frontend you can remove the authentication configuration. However you need the change the NNTP user and password in `config.php` to the user that should be used by the NNTP connection.
3. Customize the `include/config.php` file. At least set the following options:
	- Put the connection data to your NNTP server into `nntp` → `uri`
	- Update the domain names used by the `sender_address` and `sender_is_self` functions.
4. The `cache` and `unread-tracker` directories need to be writable by the webserver. The frontend will use these directories for temporary data and to keep track what the users already red.
5. Activate the cronjob `cron-jobs/clean-expired-trackers.php`. On Debian based systems this can be done by creating a symbolic link to `clean-expired-trackers.php` in the directory ´/etc/cron.daily`. The cronjob needs the PHP5 command line interface to run (the `php5-cli` package).

That should do the job. Not as easy as it could be. Sorry for that.

[i1]: http://httpd.apache.org/docs/2.2/howto/auth.html

## Feedback

Comments and ideas are always welcome. You can [open an issue][f1], [post a comment][f2] on my weblog or send me a mail at <stephan.soller@helionweb.de>.

[f1]: https://github.com/arkanis/nntp-forum/issues
[f2]: http://arkanis.de/weblog/2011-06-13-nntp-forum-download-and-installation-guide

## The MIT License

Copyright (c) 2011 Stephan Soller <stephan.soller@helionweb.de>.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
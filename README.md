# NeoCMS

## Version 2.0

Welcome to NeoCMS, the super lightweight, database-free content management system! Built with PHP and JavaScript, NeoCMS
is designed to be fast and easy to use. No complicated setup required—just drop it into your static website, and you’re
good to go.

Version 2.0 is a complete overhaul to support the latest versions of jQuery and TinyMCE (GPL). We’ve made things even
smoother for your content editing experience.

## Features

* No Database Required: NeoCMS works without a database, so you don’t have to worry about setup hassles or performance
  slowdowns.
* Easy Content Editing: With our intuitive WYSIWYG editor, anyone can update content without writing a single line of
  code. Adding the additional class *neo-dupe* will allow an element and it's children to be cloned.
* Seamless Integration: Simply add the CMS to any existing static website. Just assign a special class to the elements
  you want to make editable, and you’re all set.
* Consistent Layouts: NeoCMS supports page templates to keep your layouts neat and uniform across all pages.

## Installation Guide

Ready to get started? Just follow these simple steps:

* Drop the CMS folder into your static website.
* Add the uploads folder to your site as well.
* Open /cms/config.php and edit the credentials for any users you want to set up.
* Update the div elements you want to make editable by adding the class editable.
* Visit /cms/, log in, and start editing!

That’s it! You’re up and running.

## Requirements

* PHP 8 running on Linux

## Known Bugs & Issues

* Testing: The system is functional, but it hasn’t been fully tested in all scenarios. Uploading and embedding youtube videos works perfectly!

## License

NeoCMS is open-source software licensed under the GNU General Public License v3.0 (GPLv3). You are free to use, modify,
and distribute this software under the terms of this license.

For more information, see the full GPLv3 License found in LICENSE.

## Found a Bug?

If you run into any issues or have feature requests, please submit them on our GitHub issues page. Your feedback helps
us improve NeoCMS!

# jtsm

This is a basic web app framework for PHP 5.4.
It is currently in a very rudimentary state.

## Advantages

 - Works on PHP 5.4 - you don't need the latest!
 - Does not require any URL rewriting - can work with nginx on a server
   you don't control
 - Stores all configuration information in .php files - can work on servers
   that do not block access to specific files or directories, or that do not
   provide space outside the document root for storage.

## Usage

Subclass `\JTSM\App` and say `(new YourApp($site_root))->run();`.

### Site layout

    site root (provided by you as a constructor parameter)
    |
    +--- skins (base directory for Twig files)
         |
         +--- cache (Twig cache directory)

Other than that, everything is up to you.

## Installing

I'm sorry to say this uses an update of nikic/fast-route that I haven't
submitted a PR for yet.  Here's how I install:

 1. Create a directory for your new site.  In this doc, that directory is
    called `base`.
 1. Copy <./composer.json.template> to `base/composer.json`.
 1. In `base`, `composer update`.

After that, you can set up a very bare-bones site:


 1. Copy <./index.php.template> to `base/index.php`.
 1. Copy <./root.twig.php.template> to `base/skins/root.twig.php`
 1. In `base`, run `php -S localhost:1337`
 1. In a browser, open <http://localhost:1337>.

You should have a basic site running!

## Legal

Copyright (c) 2016 Chris White (cxw42@github) (<http://www.devwrench.com>).
LGPL-3+.  See <LICENSE.md> for details.


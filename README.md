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

## Site layout

    site root (provided by you as a constructor parameter)
    |
    +--- skins (base directory for Twig files)
         |
         +--- cache (Twig cache directory)

Other than that, everything is up to you.

Copyright (c) 2016 Chris White (cxw42@github) (<http://www.devwrench.com>)


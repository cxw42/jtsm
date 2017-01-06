{# <?php die();?> #}
{# Not actually PHP, but rather a Twig template!
   Begin all your templates with the line above so that the details will not
   be visible to someone trying to access your templates as files directly. #}

Howdy!

Check out <a href="{{gen('user',id=42)}}">user 42!</a>

{# vi: set ts=4 sts=4 sw=4 et ai ff=unix: #}


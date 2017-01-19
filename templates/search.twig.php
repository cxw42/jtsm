{# <?php die();?> #}

Search!

<form name="searchform" method="get" action="/">
<label>Query: <input type="text" name="q" /></label>
<input type="hidden" name="p" value="/search" />{# TODO gen this URL #}
<input type="submit" />
</form>

{% if query is defined %}
Query: {{ query }}
{% endif %}

Back to the <a href="{{gen('users')}}">Users list</a>

{# vi: set ts=4 sts=4 sw=4 et ai ff=unix: #}


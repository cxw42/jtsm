{# <?php die();?> #}

Search!

<form name="searchform" method="get" action="/">
<label>Query: <input type="text" name="q" /></label>
<input type="hidden" name="p" value="/search" />{# TODO gen this URL #}
<input type="submit" />
</form>

{% if query is defined %}
<hr />
Query: {{ query }}
{% endif %}{# query #}

{% if hits is defined %}
<hr /><h2>Results</h2>
<ol>
{% for hit in hits %}
    <li>[{{ hit.score }}] "{{ hit.title|default('Unknown title') }}", {#
        #}a {{ hit.world|default('unknown-world') }} resource</li>
{% endfor %}
</ol>
{% endif %}{# hits #}

{# vi: set ts=4 sts=4 sw=4 et ai ff=unix: #}


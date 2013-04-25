<div id="topBars">

	{include file="CoreHome/templates/top_bar_top_menu.tpl"}
	{include file="CoreHome/templates/top_bar_hello_menu.tpl"}

</div>

{if $showSitesSelection}
<div class="top_bar_sites_selector">
    <label>{'General_Website'|translate}</label>
    {include file="CoreHome/templates/sites_selection.tpl"}
</div>
{/if}

<script type="text/javascript">

    {literal}
    $(document).ready(function(){
        function getUrlVars()
        {
            var vars = [], hash;
            var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
            for(var i = 0; i < hashes.length; i++)
            {
                hash = hashes[i].split('=');
                vars.push(hash[0]);
                vars[hash[0]] = hash[1];
            }
            return vars;
        }
        vars = getUrlVars();
        $.post("https://mojo.seosamba.com/plugin/piwik/run/getLinkBySiteId?jsoncallback=?",{id: vars.idSite},function(responce) {
            if(!responce.error && responce.logo != null) {
                $('#logo a img').attr('src', responce.logo);
                $('link[rel="shortcut icon"]').attr('href',responce.logo);
                $('#logo a img').css('display', 'block');
            } else {
                $('#logo a img').css('display', 'block');
            }

        }, 'jsonp')
    });
    {/literal}
</script>
<!DOCTYPE html>
<html>
    <head>
        <title>Rhino</title>
         <link rel="icon" href="{$Top.BaseHref}/_resources/themes/rhino/images/rhino.png" type="image/png"> 
        <link rel="stylesheet" type="text/css" href="{$Top.BaseHref}/_resources/themes/rhino/css/reset.css"></link>
        <link rel="stylesheet" type="text/css" href="{$Top.BaseHref}/_resources/themes/rhino/css/style.css"></link>
        <script src="{$Top.BaseHref}/_resources/themes/rhino/tablefilter/tablefilter.js"></script>
        <script src="{$Top.BaseHref}/_resources/themes/rhino/javascript/rhino-tables-page.js"></script>
    </head>
    <body>
        <header>
            <div>
                <span class="logo">
                    <img src="{$Top.BaseHref}/_resources/themes/rhino/images/rhino.png" width="12" height="12" style="margin-right:-6px">
                    RHINO
                </span>
                <% loop $HtmlTables %>
                    <a class="table-link" data-table="{$Table}" href="{$Top.BaseHref}/{$Top.URLSegment}?t={$Table}">$Table</a>
                    <% if not $Last %>|<% end_if %>
                <% end_loop %>
            </div>
            <div id="examplefilters">
                <span>Example filters:</span>
                <span>fail</span>
                <span>!depbot</span>
                <span>>= 02-2020</span>
                <span><a href="https://github.com/koalyptus/TableFilter/wiki/4.-Filter-operators" target="_blank">documentation</a></span>
            </div>
        </header>
        <div class="runs">
            <span><strong>Last run:</strong> $Top.LastRun</span> |
            <span><strong>Next run:</strong> $Top.NextRun</span>
        </div>
        <div>$Top.HtmlContent.RAW</div>
        $ABC
    </body>
</html>

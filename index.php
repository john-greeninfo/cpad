<?php

require_once 'init.php';

?>

<!doctype html>
<html>
<head>
    <link href="etc/index.css" rel="stylesheet" type="text/css"> 
</head>
<body>

<!-- 
    <div id="release_btn" class="big-buttn">
        <p>Release<br>CPAD</p>
    </div>
 -->
<!-- 
    <ul>
        <li>
            <a target="_blank" href="geoprocess.php?type=holdings" class="btn btn-default btn-lg">Holdings</a>
        </li>
        <li>
            <a target="_blank" href="geoprocess.php?type=units" class="btn btn-default btn-lg">Units</a>
        </li>
        <li>
            <a target="_blank" href="geoprocess.php?type=superunits_nma" class="btn btn-default btn-lg">SuperUnits (nma)</a>
        </li>
        <li>
            <a target="_blank" href="units.php" class="btn btn-default btn-lg">CCED</a>
        </li>
        <li>
            <a target="_blank" href="units.php" class="btn btn-default btn-lg">NCED-CA</a>
        </li>
    </ul>
 -->

<form id="get_cpad_form" action="main.php">
</form>


    <!-- jQuery and jQuery UI -->
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>
    <link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/start/jquery-ui.css">

    <!-- jQuery UI overrides, cuz the ThemeRoller doesn't let us customize anything we want to customize, and no existing theme is exactly the colors we like -->
    <link rel="stylesheet" href="http://comap.cnhp.colostate.edu/dev/mapcollab/application/views/site/jqui.css" media="all">

<script>
    var build_all = true, download_all = true;

    $('#release_btn').click(function(){
        
        $.ajax({ 
            url: 'release.php',
            //data: {"bookID": book_id},
            type: 'post',
            success: function(result) {
                
            }
        });

    });

    var products = ['Superunits NMA','Units', 'Holdings', 'CPAD Stats', 'CCED', 'NCED-CA'];
    var row = '<table class="table"><thead><tr>';
    row += '<th class="">SHP</th>';
    row += '<th class="">Build <input id="build_toggle" class="grp_togglr" data-grp="chk_build" type="checkbox" checked="checked" /></th>';
    row += '<th class="">Download <input id="download_toggle" class="grp_togglr" data-grp="chk_download" type="checkbox" checked="checked" /></th>';
    row += '</tr></thead>';
    //$('#get_cpad_form').append(row);
    
    for (var i = 0; i < products.length; i++) {
        
        row += '<tr><td>' + products[i] + '</td><td><input class="chk_build" type="checkbox" checked="checked" /></td><td><input class="chk_download" type="checkbox" checked="checked" /></td></tr>';
    };    
        //$('#get_cpad_form').append(row);

    row += '</table>';

    $('#get_cpad_form').append(row);

    $('.grp_togglr').click(function(){
        var grp = $(this).attr('data-grp');
        console.log(grp);
        console.log( $(this).prop('checked') );
        $('.'+grp).prop('checked', $(this).prop('checked') );
    });


</script>

</body>
</html>
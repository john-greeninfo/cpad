<?php

require_once 'init.php';
ini_set('max_execution_time', 3600); // 3600s = 1hr

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

//echo "<pre>";
//echo "Running...";
//$grab_people = pg_query("SELECT * FROM people WHERE id_num = 3761832");
//$person = pg_fetch_assoc($grab_people);

$cnx_str1 = "pgsql:host=".PG_HOST;
$cnx_str1 .= ";dbname=" . PG_DATABASE;
$cnx_str1 .= ";user=" . PG_USER;
$cnx_str1 .= ";password=" . PG_PASSWORD;

//echo $cnx_str1;

try {
  $dbh = new PDO($cnx_str1); 
  # SQLite Database
  //$DBH = new PDO("sqlite:my/database/path/database.db");
}
catch(PDOException $e) {
    echo $e->getMessage();
}

$i = 1;

$type = 'holdings';

switch ($type) {
    case 'holdings':
        $table = "mat_view_holdings_mgmt_for_edits";
    /*default:
        return print "Bad type";
        break;*/

}

process_dataset($type);
download_table($table);

function process_dataset($dataset='') {

}

// public function contributions_download($type='') {
function download_table($table='') {
    // from the given $type figure out some params, e.g. the table to download  ... or that we got as bunk type and should bail
    // $type = "cpad_holdings_nightly";
    $out_name = "cpad_holdings_nightly";
    $temp_url = "";

    // make up filenames and a temp directory, for the shapefile
    $random    = md5(mt_rand() . microtime());
    //$temp_dir  = sprintf("%s/%s", TEMP_DIR, $random );
    $temp_dir  = sprintf("%s", "nightly" );
    $shp       = sprintf("%s/%s.shp", $temp_dir, $out_name );
    $shx       = sprintf("%s/%s.shx", $temp_dir, $out_name );
    $dbf       = sprintf("%s/%s.dbf", $temp_dir, $out_name );
    $prj       = sprintf("%s/%s.prj", $temp_dir, $out_name );
    $zipout    = sprintf("%s/%s.zip", $temp_dir, $out_name );
    //$final_url = sprintf("%s/%s/%s.zip", $this->config->item('temp_url'), $random, $type );
    $final_url = sprintf("%s/%s.zip", $temp_dir, $out_name );
    
    // mkdir($temp_dir);

    // shp2pgsql or...
    /*$command = sprintf("%s -f %s -r -u %s -P %s %s %s",
        $this->config->item('pgsql2shp'),
        escapeshellarg($shp),
        escapeshellarg($this->db->username), escapeshellarg($this->db->password),
        escapeshellarg($this->db->database), escapeshellarg($table)
    );*/

    // ogr2ogr
    // ogr2ogr --config SHAPE_ENCODING "UTF-8" -lco ENCODING="UTF-8" file.shp  pg:"dbname=db user=user password=password" table
    $command = sprintf("C:\OSGeo4W64\bin\ogr2ogr.exe --config SHAPE_ENCODING 'UTF-8' -lco ENCODING='UTF-8' %s  pg:\"dbname=%s user=%s password=%s\" %s -t_srs epsg:3310 -overwrite -nlt MultiPolygon ",
        escapeshellarg($shp),
        escapeshellarg(PG_DATABASE),
        escapeshellarg(PG_USER),
        escapeshellarg(PG_PASSWORD),
        escapeshellarg($table)
    );

    echo $command;
    `$command`;

    exit;

    // copy to P drive
    $cmd = "cp nightly/* " . NIGHTLY_PDIR;
    `$cmd`;

    // ZIP it up
    $zip = new ZipArchive();
    if ($zip->open($zipout, ZipArchive::CREATE)!==TRUE) die("cannot create ZIP file $zipout\n");
    $zip->addFile($shp, basename($shp) );
    $zip->addFile($shx, basename($shx) );
    $zip->addFile($dbf, basename($dbf) );
    $zip->addFile($prj, basename($prj) );
    $zip->close();

    // ready!
    //return print $final_url;

    header('Content-disposition: attachment; filename="'. basename($final_url) . '";');
    header('Content-type: application/zip');
    header('Content-Length: ' . filesize($final_url));
    readfile($final_url);

    exit;

}


?>

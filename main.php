<?php

// C:\xampp\php\php.exe "X:\GreenInfoTools\cpad\nightly\main.php"
// C:\xampp\php\php.exe "C:\xampp\htdocs\GreenInfoTools\cpad\nightly\main.php"

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

$dd_fuzz = "0.00001";

try {
  $dbh = new PDO($cnx_str1);
  # SQLite Database
  //$DBH = new PDO("sqlite:my/database/path/database.db");
}
catch(PDOException $e) {
    echo $e->getMessage();
}

$i = 1;


$dbh->prepare("REFRESH MATERIALIZED VIEW mat_view_holdings_mgmt_for_edits;")->execute();


$table = "mat_view_holdings_mgmt_for_edits";
$published_name = "cpad_holdings_nightly";
export_table($table,$published_name);

$table = "cpad_units";
$published_name = "cpad_units_nightly";
process_units($dbh, $table);
export_table($table,$published_name);

$table = "cpad_superunits_nma";
$published_name = "cpad_superunits_nma_nightly";
process_su_nma($dbh, $table, $dd_fuzz);
export_table($table,$published_name);



/*$sql = "select count(*) from master_holding";
$statement = $dbh->prepare($sql);
$bool_did_execute=$statement->execute();
$results = $statement->fetchAll();

var_dump($results);*/

//download_table($table, $published_name);

function process_units($dbh, $table) {
    //$dbh->prepare("DROP TABLE temp_unit_id")->execute();
    $dbh->prepare("DROP TABLE cpad_units")->execute();

    $make_holdings_valid = "update master_holding set wkb_geometry = st_multi(st_makevalid(st_buffer(wkb_geometry,0))) where not st_isvalid(wkb_geometry)";
    $dbh->prepare($make_holdings_valid)->execute();


$sql = <<<EOT
CREATE TABLE temp_unit_id as
  select
  --distinct on (u.park_name, u.ACCESS_TYP, u.manager_id)
    st_multi(
        st_makevalid(
            st_buffer(
                st_union(h.wkb_geometry)
            ,0)
        )
    ) as wkb_geometry,
    h.unit_id,
    h.cff
  from
    master_holding h
  group by
    h.unit_id,
    h.cff;
EOT;

    //echo "\n $sql \n";

    $dbh->prepare($sql)->execute();


$sql = <<<EOT
CREATE TABLE $table AS
SELECT
  tu.unit_id,
  tu.wkb_geometry,
  --ST_Multi(tu.st_union) as wkb_geometry,
  u.sub_name as unit_name,
  u.access_typ,
  u.mng_ag_id,
  u.park_url,
  tu.cff,
  u.county,
  u.own_type,
  u.p_des_tp,
  a.agncy_id,
  a.agncy_name,
  a.agncy_lev,
  a.agncy_typ,
  a.layer,
  a.agncy_web,
  a.gap_ag_cod,
  a.gap_ag_nam,
  a.gap_ag_typ,
  a.agncy_uid,
  u.label_name
FROM
  temp_unit_id tu,
  master_unit u,
  master_agency a
  --master_park_name p
WHERE
  tu.unit_id = u.unit_id
  AND u.agncy_id = a.agncy_id;
EOT;

    //echo "\n $sql \n";

    $dbh->prepare($sql)->execute();


    $dbh->prepare("ALTER TABLE $table ADD COLUMN acres numeric(15,3)")->execute();
    $dbh->prepare("UPDATE $table SET acres = st_area(st_transform(wkb_geometry,3310))::numeric / 4046.872609874252")->execute();

    run_stats($dbh);

}

function process_su_nma($dbh, $table, $dd_fuzz) {
    $dbh->prepare("DROP TABLE $table")->execute();

    /*
    union geoms from units
    */

$sql = <<<EOT
CREATE TABLE $table as
  select
    su.suid_nma,
    st_multi(
        st_makevalid(
            st_union(
                st_buffer(wkb_geometry,$dd_fuzz)
            )
        )
    ) as wkb_geometry,
    u.unit_name AS park_name,
    u.access_typ,
    u.mng_ag_id AS manager_id,
    a.agncy_name,
    a.agncy_lev,
    a.agncy_typ,
    a.layer,
    a.agncy_web,
    u.park_url,
    u.label_name
  from
    cpad_units u
  LEFT JOIN
    master_agency a
  ON
    u.mng_ag_id = a.agncy_id
  LEFT JOIN
    master_su_nma su
  ON u.unit_name = su.park_name
  AND u.mng_ag_id = su.manager_id
  AND u.access_typ = su.access_typ
  group by
    su.suid_nma,
    u.unit_name,
    u.access_typ,
    u.mng_ag_id,
    a.agncy_name,
    a.agncy_lev,
    a.agncy_typ,
    a.layer,
    a.agncy_web,
    u.park_url,
    u.label_name;
EOT;

    // ~13,800 rows
    // ~9 minutes buffer 0
    // ~15 min if plus and minus buffering

    echo "\n$sql\n";
    $dbh->prepare($sql)->execute();

    $sql = <<<EOT
test
EOT;

    //$dbh->prepare($sql)->execute();


    // now unbuffer to get back to correct borders
    // and simplify to get rid of buffer's excess vertices
$sql = <<<EOT
UPDATE $table
SET wkb_geometry =
    st_multi(
        ST_SimplifyPreserveTopology(
            st_makevalid(
                st_buffer(wkb_geometry,-$dd_fuzz)
            )
        ,$dd_fuzz)
    )
EOT;
// 13 min

    $dbh->prepare($sql)->execute();
    $dbh->prepare("ALTER TABLE $table ADD COLUMN acres numeric(15,3)")->execute();
    $dbh->prepare("UPDATE $table SET acres = st_area(st_transform(wkb_geometry,3310))::numeric / 4046.872609874252")->execute();

    run_stats($dbh);

}

function export_table($table='',$out_name='') {
    // from the given $type figure out some params, e.g. the table to download  ... or that we got as bunk type and should bail
    // $type = "cpad_holdings_nightly";
    //$out_name = "cpad_holdings_nightly";
    $temp_url = "";

    // make up filenames and a temp directory, for the shapefile
    $random    = md5(mt_rand() . microtime());
    //$temp_dir  = sprintf("%s/%s", TEMP_DIR, $random );
    //$temp_dir  = sprintf("%s", "nightly" );
    $temp_dir  = realpath(dirname(__FILE__)) . "\\nightly\\";
    echo $temp_dir;
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

    chdir( realpath(dirname(__FILE__)) );

    $command = sprintf("C:\OSGeo4W64\bin\ogr2ogr.exe --config SHAPE_ENCODING UTF-8 -lco ENCODING=UTF-8 %s  pg:\"dbname=%s user=%s password=%s host=%s\" %s -t_srs epsg:3310 -overwrite -nlt MultiPolygon ",
        escapeshellarg($shp),
        escapeshellarg(PG_DATABASE),
        escapeshellarg(PG_USER),
        escapeshellarg(PG_PASSWORD),
        escapeshellarg(PG_HOST),
        escapeshellarg($table)
    );

    //echo $command;
    `$command`;

    // copy to P drive
    $cmd = "cp -p data/* " . NIGHTLY_PDIR;
    `$cmd`;

    // ZIP it up
    $zip = new ZipArchive();
    if ($zip->open($zipout, ZipArchive::CREATE)!==TRUE) die("cannot create ZIP file $zipout\n");
    $zip->addFile($shp, basename($shp) );
    $zip->addFile($shx, basename($shx) );
    $zip->addFile($dbf, basename($dbf) );
    $zip->addFile($prj, basename($prj) );
    $zip->close();

}

// public function contributions_download($type='') {
function download_table($table='',$out_name='') {

    // ready!
    //return print $final_url;

    header('Content-disposition: attachment; filename="'. basename($final_url) . '";');
    header('Content-type: application/zip');
    header('Content-Length: ' . filesize($final_url));
    readfile($final_url);

    exit;

}

function run_stats($dbh) {

$dbh->prepare("REFRESH MATERIALIZED VIEW mat_view_holdings_mgmt_for_edits;")->execute();


$sql = <<<EOT
INSERT INTO cpad_stats_history (
    total_acres,
    agency_count,
    holding_count,
    unit_count,
    su_nma_count,
    holding_verts,
    unit_verts,
    su_nma_verts
)
VALUES (
    (SELECT
        SUM(st_area(st_transform(wkb_geometry,3310))::numeric) / 4046.872609874252
    FROM master_holding
    ),
    (SELECT
        COUNT(DISTINCT agncy_id)
    FROM cpad_units
    ),
    (SELECT
        COUNT(*)
    FROM master_holding
    ),
    (SELECT
        COUNT(*)
    FROM cpad_units
    ),
    (SELECT
        COUNT(*)
    FROM cpad_superunits_nma
    ),
    (SELECT
        SUM(ST_NPoints(wkb_geometry)) -- 4.5 million
    FROM mat_view_holdings_mgmt_for_edits),
    (SELECT
        SUM(ST_NPoints(wkb_geometry)) --  million
    FROM cpad_units),
    (SELECT
        SUM(ST_NPoints(wkb_geometry)) --  million
    FROM cpad_superunits_nma
    )
)
;


EOT;

    //echo "\n $sql \n";
    $dbh->prepare($sql)->execute();




$sql = <<<EOT
INSERT INTO cpad_stats_history (
    cdpr_acres,
    cdfw_acres,
    slc_acres,
    blm_acres,
    usfws_acres,
    usfs_acres,
    nps_acres
)
SELECT ar[1], ar[2], ar[3], ar[4], ar[5], ar[6], ar[7]
FROM
  (SELECT
    array(
        SELECT
          sum(gis_acres)::int
        from mat_view_holdings_mgmt_for_edits
        where agncy_name in (
          'California Department of Fish and Wildlife',
          'California Department of Parks and Recreation',
          'California State Lands Commission',
          'United States Bureau of Land Management',
          'United States Fish and Wildlife Service',
          'United States Forest Service',
          'United States National Park Service'
        )
        --and access_typ = 'Open Access'
        group by agncy_name, agncy_id
        order by agncy_name
        ) AS ar
  FROM mat_view_holdings_mgmt_for_edits
  limit 1
  )
  AS subq
;
EOT;

    //echo "\n $sql \n";

    $dbh->prepare($sql)->execute();

    $temp_dir  = realpath(dirname(__FILE__)) . "\\data\\";

    chdir( $temp_dir );

    $command = "rm cpad_stats_history.csv";
    `$command`;

    $command = sprintf("C:\OSGeo4W64\bin\ogr2ogr.exe -f csv cpad_stats_history.csv  pg:\"dbname=%s user=%s password=%s host=%s\" cpad_stats_history -sql \"select stats_date, total_acres, agency_count, unit_count, unit_verts, cdpr_acres, cdfw_acres, slc_acres, blm_acres, usfws_acres, usfs_acres, nps_acres from cpad_stats_history order by stats_date\" ",
        escapeshellarg(PG_DATABASE),
        escapeshellarg(PG_USER),
        escapeshellarg(PG_PASSWORD),
        escapeshellarg(PG_HOST)
    );

    //echo $command;
    `$command`;

}


?>

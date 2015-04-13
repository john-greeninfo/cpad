<?php

require_once 'config/config.php';
ini_set('max_execution_time', 3600); // 3600s = 1hr
echo "<pre>";
echo "Running...";
//$grab_people = pg_query("SELECT * FROM people WHERE id_num = 3761832");
//$person = pg_fetch_assoc($grab_people);

$cnx_str1 = "pgsql:host='ginserver';dbname=" . $CONFIG['DB_BASE'];

try {
  $dbh = new PDO($cnx_str1, $CONFIG['DB_USER'], $CONFIG['DB_PASS']); 
  # SQLite Database
  //$DBH = new PDO("sqlite:my/database/path/database.db");
}
catch(PDOException $e) {
    echo $e->getMessage();
}

$i = 1;


// load shp to postgis
//$cmd = "$ogr2  -lco PRECISION=NO PG:'dbname=$DB_NAME password=$PASSWORD user=$USER' -update -append projnesser.shp -overwrite -nlt MULTIPOLYGON -a_srs epsg:3857"; // 26913 = utm 13 (colorado)
//`$cmd`;

/*$sql = "
SELECT SUM(gis_acres) as gis_acres, mng_agncy FROM su_nma_unbuffer GROUP BY mng_agncy ORDER BY SUM(gis_acres) DESC LIMIT 10
";

$result = $dbh->prepare( $sql );
$result->execute();

$rows = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";

foreach ($rows as $row) {
    print_r($row);
}
*/


// 1. Create the unit geoms (dissolve/union/aggregate)

$statement = $dbh->prepare( "DROP TABLE temp_unit_id" )->execute();

$sql = "CREATE TABLE temp_unit_id as
  select
    st_multi(st_union(h.wkb_geometry)) as wkb_geometry,
    h.unit_id,
    ''::text AS park_name,
    ''::text AS access_typ,
    0::integer AS manager_id,
    ''::text AS park_url
  from
    master_holding h
  group by
    h.unit_id";
/*
$statement = $dbh->prepare($sql)->execute();
*/
// ~2.5m
echo "\n $i. $sql";
$i++;

// 2. Update the unit geoms with attributes from master_unit

$sql = "UPDATE temp_unit_id
SET
  park_name = u.sub_name,
  access_typ = u.access_typ,
  manager_id = u.mng_ag_id,
  park_url = u.park_url
FROM
  master_unit u
WHERE
  temp_unit_id.unit_id = u.unit_id";

$statement = $dbh->prepare($sql)->execute();

echo "\n $i. $sql";
$i++;
if($dbh->errorCode() !== 0) {
    $errors = $dbh->errorInfo();
    echo($errors[2]);
}

// ~


// 2.b (Publish complete units shp)

/*$sql = "CREATE TABLE cpad_units AS
SELECT 
  tu.unit_id, 
  tu.wkb_geometry, 
  tu.park_name as unit_name, 
  tu.access_typ, 
  tu.manager_id, 
  tu.park_url,
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
  p.label_name
FROM 
  temp_unit_id tu,
  master_unit u,
  master_agency a,
  master_park_name p 
WHERE
  tu.unit_id = u.unit_id
  AND u.agncy_id = a.agncy_id
  AND tu.park_name = p.sub_name";

$statement = $dbh->prepare($sql)->execute();*/
// ~



// 3.
$statement = $dbh->prepare( "DROP TABLE temp_su_nma" )->execute();

$sql = "CREATE TABLE temp_su_nma as
  select
    st_multi(st_union(wkb_geometry)) as wkb_geometry,
    park_name,
    access_typ,
    manager_id,
    park_url
  from
    temp_unit_id
  group by
    park_name,
    access_typ,
    manager_id,
    park_url";

$statement = $dbh->prepare($sql)->execute();

echo "\n $i. $sql";
$i++;
if($dbh->errorCode() !== 0) {
    $errors = $dbh->errorInfo();
    echo($errors[2]);
}

// ~3m


/*
3.1 (IMPORTANT)
If combo not in master_su_nma, insert it (with autoincrement)

INSERT INTO master_su_nma
  (suid_nma, park_name, manager_id, access_typ)
SELECT nextval('master_su_nma_suid_nma_seq'), c.park_name, c.manager_id, c.access_typ
FROM temp_su_nma c
WHERE (c.park_name, c.manager_id, c.access_typ)
NOT IN
(
  SELECT s.park_name, s.manager_id, s.access_typ
  FROM master_su_nma s
)
GROUP BY c.park_name, c.manager_id, c.access_typ
;
*/


// 4.
$statement = $dbh->prepare( "ALTER TABLE temp_su_nma ADD COLUMN suid_nma bigint" )->execute();
$statement = $dbh->prepare( "ALTER TABLE temp_su_nma ADD COLUMN su_status text" )->execute();

$statement = $dbh->prepare( "ALTER TABLE temp_su_nma ADD COLUMN mng_agncy text" )->execute();
$statement = $dbh->prepare( "ALTER TABLE temp_su_nma ADD COLUMN layer text" )->execute();
$statement = $dbh->prepare( "ALTER TABLE temp_su_nma ADD COLUMN label_name text" )->execute();
$statement = $dbh->prepare( "ALTER TABLE temp_su_nma ADD COLUMN gis_acres numeric(15,3)" )->execute();

$sql = "UPDATE temp_su_nma t
SET
  suid_nma = su.suid_nma,
  su_status = su.su_status
FROM
  master_su_nma su
WHERE
  t.park_name = su.park_name
  AND t.access_typ = su.access_typ
  AND t.manager_id = su.manager_id
";

$statement = $dbh->prepare($sql)->execute();

echo "\n $i. $sql";
$i++;
if($dbh->errorCode() !== 0) {
    $errors = $dbh->errorInfo();
    echo($errors[2]);
}

// ~

// 5. Test unique id:
$sql = "select count(*), park_name, manager_id, access_typ from temp_su_nma
group by park_name, manager_id, access_typ
having count(*) > 1";

$statement = $dbh->prepare($sql)->execute();

$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    print_r($row);
}

echo "\n $i. $sql";
$i++;
if($dbh->errorCode() !== 0) {
    $errors = $dbh->errorInfo();
    echo($errors[2]);
}


// 6. Clean geom
$statement = $dbh->prepare("UPDATE temp_su_nma set wkb_geometry = ST_MakeValid(wkb_geometry) where not ST_IsValid(wkb_geometry)")->execute();
;

echo "\n $i. $sql";
$i++;
if($dbh->errorCode() !== 0) {
    $errors = $dbh->errorInfo();
    echo($errors[2]);
}

// 5m; 0 rows

// 7. Additional attributes:

$sql = "UPDATE temp_su_nma set gis_acres = (st_area(st_transform(wkb_geometry,3310)))::numeric / 4046.872609874252";
$statement = $dbh->prepare("$sql")->execute();

$sql = "UPDATE temp_su_nma SET layer = a.layer, mng_agncy = a.agncy_name FROM master_agency a WHERE manager_id = a.agncy_id";
$statement = $dbh->prepare("$sql")->execute();

$sql = "UPDATE temp_su_nma SET label_name = p.label_name FROM master_park_name p WHERE park_name = p.sub_name";
$statement = $dbh->prepare("$sql")->execute();

echo "\n $i. $sql";
$i++;
if($dbh->errorCode() !== 0) {
    $errors = $dbh->errorInfo();
    echo($errors[2]);
}

// 9. Fill sliver holes & simplify to remove extraneous vertices:



// ogr2ogr cpad_2014b8_superunits_name_manager_access.shp  pg:"host=ginserver dbname=cpad_2015 user=postgres password=ginfo116" su_nma_unbuffer -overwrite -t_srs epsg:3310



echo "Done.";

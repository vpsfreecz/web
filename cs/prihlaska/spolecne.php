<?php
require '../../config.php';
require '../../lib/db.lib.php';

$db = new sql_db (DB_HOST, DB_USER, DB_PASS, DB_NAME);
?>

<label for="name">Doplňující údaje</label>
<div class="row">
	<div class="col-xs-12 form-group">
		<input type="text" class="form-control" id="how" name="how" placeholder="Jak ses o nás dozvěděl?">
	</div>

</div>

<div class="row">
	<div class="col-xs-12 form-group">
		<input type="text" class="form-control" id="note" name="note" placeholder="Poznámky">
	</div>

</div>

<label for="name">Nastavení VPS</label>
<div class="row">
	<div class="col-xs-12 form-group">
		<select class="form-control" name="distribution">
			<option disabled selected>Vyber si distribuci</option>
			<?php
			while($tpl = $db->findByColumn("cfg_templates", "templ_supported", "1", "templ_order, templ_label"))
				echo '<option value="'.$tpl["templ_id"].'">'.$tpl["templ_label"].'</option>';
			?>
		</select>
	</div>

</div>

<div class="row">
	<div class="col-xs-12 form-group">
		<select class="form-control" name="location">
			<option disabled selected>Preferovaná lokace pro VPS</option>
			<?php
			$sql = 'SELECT location_id, location_label
					FROM locations l
					INNER JOIN servers s ON l.location_id = s.server_location
					WHERE s.environment_id = '.$db->check(ENVIRONMENT_ID).'
					GROUP BY location_id
					ORDER BY location_id';
				$rs = $db->query($sql);
				while ($loc = $db->fetch_array($rs)) {
					echo '<option value="'.$loc["location_id"].'">';
					echo 'Master Internet '.$loc["location_label"];
					echo '</option>';
				}
			?>
		</select>
	</div>

</div>

<div class="row">
	<div class="col-xs-12 form-group">
		<select class="form-control" name="currency">
			<option disabled selected>Měna platby členského poplatku</option>
			<option value="czk">900 Kč na tři měsíce</option>
			<option value="eur">36 eur na tři měsíce</option>
		</select>
	</div>
<p>Chceš-li fakturu, pošli po schválení přihlášky na <a href="/kontakt/">podporu</a> fakturační údaje.</p>
</div>


<div class="row">
	<button class="btn btn-default" type="submit">Odeslat</button>
</div>

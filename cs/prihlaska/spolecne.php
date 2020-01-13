<?php
require_once dirname(__FILE__).'/../../lib/init.php';

if (!$api)
	$api = new \HaveAPI\Client(API_URL);

$templates = array();
$locations = array();

foreach ($api->os_template->list() as $tpl) {
	$templates[ $tpl->id ] = array(
		'id' => $tpl->id,
		'label' => $tpl->label,
		'hypervisor_type' => $tpl->hypervisor_type,
	);
}

$locList = $api->location->list(array(
	'environment' => ENVIRONMENT_ID,
	'has_hypervisor' => true,
));

foreach ($locList as $loc) {
	$locations[$loc->id] = array(
		'label' => $loc->label,
	);
}
?>

<label for="name">Doplňující údaje</label>
<div class="row">
	<div class="col-xs-12 form-group">
		<?php $f->input('how') ?>
	</div>

</div>

<div class="row">
	<div class="col-xs-12 form-group">
		<?php $f->input('note'); ?>
	</div>

</div>

<label for="location">Umístění VPS</label>
<div class="row">
	<div class="col-xs-12 form-group">
			<?php
			$opts = array();

			foreach ($locList as $loc) {
				$opts[ $loc->id ] = 'Master Internet '.$loc->label;
			}

			$f->select('location', $opts);
		?>
	</div>

</div>

<label>Virtualizační platforma</label>
<div class="row">
	<noscript>Prosím zapni si JavaScript</noscript>
	<div id="platform-choose" class="col-xs-12 hidden">
		<p>
			V současné době používáme dvě virtualizační technologie. V Praze postupně
			rozšiřujeme nasazení <strong>vpsAdminOS</strong>, což je námi vyvinutý systém
			pro kontejnerovou virtualizaci. Více informací viz
			<a href="https://kb.vpsfree.cz/navody/vps/vpsadminos" target="_blank">znalostní báze</a>
			nebo dokumentace projektu na
			<a href="https://vpsadminos.org" target="_blank">vpsadminos.org</a>.
		</p>
		<p>
			V Brně je k prozatím dispozici jen <strong>OpenVZ Legacy</strong>. Jedná
			se také o kontejnerovou virtualizaci, ale používá se zde starší Linux
			kernel a postupně od ní upouštíme ve prospěch <strong>vpsAdminOS</strong>.
		</p>
	</div>
	<p id="platform-vpsadminos" class="hidden">
		<strong>vpsAdminOS</strong> je naším spolkem vyvinutý systém pro provoz
		linuxových kontejnerů, které pohání naše VPS. Více informací viz
		<a href="https://kb.vpsfree.cz/navody/vps/vpsadminos" target="_blank">znalostní báze</a>
		nebo dokumentace projektu na
		<a href="https://vpsadminos.org" target="_blank">vpsadminos.org</a>.
	</p>
	<p id="platform-openvz" class="hidden">
		<strong>OpenVZ Legacy</strong> je dosluhující kontejnerová virtualizace.
		V současné době přecházíme na nové řešení v podobě <strong>vpsAdminOS</strong>,
		které je však zatím k dispozici pouze v Praze. Více informací viz
		<a href="https://kb.vpsfree.cz/navody/vps/vpsadminos" target="_blank">znalostní báze</a>.
	</p>
</div>

<label>Distribuce</label>
<div class="row">
	<div class="col-xs-12 form-group">
		<?php
			$opts = array();

			foreach ($templates as $id => $tpl) {
				$opts[ $id ] = '['.$tpl['hypervisor_type'].'] '.$tpl['label'];
			}

			$f->select('distribution', $opts);
		?>
	</div>

</div>

<div class="row">
	<div class="col-xs-12 form-group">
		<?php
			$f->select('currency', array(
				'czk' => '900 Kč na tři měsíce',
				'eur' => '36 € na tři měsíce',
			));
		?>
	</div>
<p>Chceš-li fakturu, pošli po schválení přihlášky na <a href="/kontakt/">podporu</a> fakturační údaje.</p>
</div>

<?php
if ($f->isResubmit()) {
	$f->input('id', 'hidden');
	$f->input('token', 'hidden');
}
?>

<div class="row">
	<input class="btn btn-default" type="submit" id="send" value="Odeslat">
</div>

<script type="text/javascript">
var locations = <?php echo json_encode($locations); ?>;
var templates = <?php echo json_encode($templates); ?>;

$('#platform-choose').removeClass('hidden');
$('#distribution option:not([disabled])').remove();

function changeLocation() {
	var locId = $('#location').val().toString();
	var loc = locations[locId];
	var platform;

	$('#platform-choose').addClass('hidden');

	if (loc.label == 'Praha') {
		platform = 'vpsadminos';
		$('#platform-openvz').addClass('hidden')
		$('#platform-vpsadminos').removeClass('hidden')
	} else {
		platform = 'openvz';
		$('#platform-openvz').removeClass('hidden')
		$('#platform-vpsadminos').addClass('hidden')
	}

	$('#distribution option:not([disabled])').remove();
	$('#distribution option[disabled]').prop('selected', true);

	for (const k of Object.keys(templates)) {
		var tpl = templates[k];

		if (tpl.hypervisor_type != platform)
			continue;

		$('#distribution').append($('<option>', {value: tpl.id, text: tpl.label}));
	}
}

$('#location').change(changeLocation);

<?php
if ($f->isPost()) {
	echo "changeLocation();\n";

	if ($_POST['distribution'])
		echo "$('#distribution').find('option[value=\"".$_POST['distribution']."\"]').prop('selected', true);\n";
}
?>
</script>

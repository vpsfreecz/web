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

<label for="name">Additional info</label>
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

<label for="location">VPS location</label>
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

<label>Virtualization platform</label>
<div class="row">
	<noscript>Please enable JavaScript</noscript>
	<p id="platform-choose" class="hidden">Choose VPS location</p>
	<p id="platform-vpsadminos" class="hidden">
		<strong>vpsAdminOS</strong> is an in-house developed container-based
		virtualization platform that powers our VPS. For more information, see our
		<a href="https://kb.vpsfree.org/manuals/vps/vpsadminos" target="_blank">knowledge base</a>
		or project documentation at
		<a href="https://vpsadminos.org" target="_blank">vpsadminos.org</a>.
	</p>
	<p id="platform-openvz" class="hidden">
		<strong>OpenVZ Legacy</strong> is a container-based virtualization platform.
		We are in the process of migrating to a more up-to-date solution called
		<strong>vpsAdminOS</strong>, which is at this time available only in Prague
		(Praha). For more information, see our
		<a href="https://kb.vpsfree.org/manuals/vps/vpsadminos" target="_blank">knowledge base</a>.
	</p>
</div>

<label>Distribution</label>
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
				'czk' => '900 CZK/three months',
				'eur' => '36 EUR/three months',
			));
		?>
	</div>
<p>If you need an invoice, please send billing information to our <a href="/contact/">support team</a>.</p>
</div>

<?php
if ($f->isResubmit()) {
	$f->input('id', 'hidden');
	$f->input('token', 'hidden');
}
?>

<div class="row">
	<input class="btn btn-default" type="submit" id="send" value="Send">
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

(function(root) {

	var state = {};
	var prefix;

	function saveState() {
		$('form input, form select').each(function (i, el) {
			state[ $(el).attr('name') ] = $(el).val();
		});
	}

	function restoreState() {
		for (var key in state) {
			if (!state.hasOwnProperty(key))
				continue;

			var el = $('form input[name="'+key+'"], form select[name="'+key+'"]');

			if (!el || state[key] === null || state[key] === undefined)
				continue;

			if (el.tagName == 'SELECT') {
				$(el).find('option[value="'+state[key]+'"]').prop('selected', true);

			} else {
				$(el).val(state[key]);
			}

			if (key != 'entity_type')
				$(el).change();
		}
	}

	function defaultTimeZone() {
		if (!window.Intl || !Intl.DateTimeFormat)
			return null;

		try {
			return Intl.DateTimeFormat().resolvedOptions().timeZone;
		} catch (e) {
			return null;
		}
	}

	function selectDefaultTimeZone() {
		var timeZone = defaultTimeZone();
		var select = $('form select[name="time_zone"]');
		var selected = select.find('option:selected');

		if (!timeZone || !select.length)
			return;

		if (selected.length && !selected.prop('disabled'))
			return;

		select.find('option').filter(function () {
			return this.value === timeZone;
		}).prop('selected', true);
	}

	function fetchForm() {
		var name;

		switch ($('#entity_type').val()) {
		case 'fyzicka':
			name = 'fyzicka-osoba';
			break;

		case 'pravnicka':
			name = 'pravnicka-osoba';
			break;

		default:
			return;
		}

		saveState();

		$.ajax({
			url: prefix+"/"+name+'/form.php',
			success: function (data) {
				$('#form-placeholder').html(data);
				restoreState();
				selectDefaultTimeZone();
			},
			error: function (xhr, textStatus) {
				$('#form-placeholder').html(
					'<p>'+
					'Unable to display the form, please try again or contact podpora@vpsfree.cz'+
					'</p>'
				);
			}
		});
	}

	root.setupRegistrationForm = function (pref) {
		prefix = pref;

		$('#continue').remove();
		$('form').attr('action', prefix + '/send.php');

		if (!$('#form-placeholder').length) {
			selectDefaultTimeZone();
			return;
		}

		$('#entity_type').change(fetchForm);
		fetchForm();
	};

}(window));

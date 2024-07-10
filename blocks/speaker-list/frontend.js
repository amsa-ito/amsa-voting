jQuery(document).ready(function($) {
    function add_speaker_list_warning_message(message){
		$('#amsa-voting-speaker-list-warning-messasge-text').text(message)
		$('#amsa-voting-speaker-list-warning-messasges').show();
	}

    $('[id^=amsa-voting-speaker-list-wrapper-]').on('submit', '[id^=nominate-speaker-form-]', function(e) {
		e.preventDefault();
		var data = $(this).serialize();
		data += '&nonce=' + Theme_Variables.nonce;
		data = data + '&nonce=' + Theme_Variables.nonce;
		
		$.post(Theme_Variables.ajax_url, data, function(response) {
			if (response.success){
				add_speaker_list_warning_message(response.data['message']);
				$('#amsa-voting-speaker-list-wrapper-' + response.data['block_id']).html(response.data['rendered_content']);
			}else{
				alert(response.data);
				console.log(response);
			}

		});
	});

	$('[id^=amsa-voting-speaker-list-wrapper-]').on('click', '.speaker-removal-button', function(e) {
		var submitButton = $(this);
		var block_id= submitButton.data('block-id');
		var data = {
			nonce: Theme_Variables.nonce,
			action: 'retract_nomination',
			block_id: block_id,
			speaker_user_id: submitButton.data('speaker-user-id') // Adjust index for 0-based array
		};
	
		// AJAX setup to handle the removal
		$.post(Theme_Variables.ajax_url, data, function(response){
			if (response.success){
				add_speaker_list_warning_message(response.data['message']);
				$('#amsa-voting-speaker-list-wrapper-'+block_id).html(response.data['rendered_content']);
			}else{
				alert(response.data);
				console.log(response);
			}
		

		});
	});

	$('#real-time-update').change(function () {
		if ($(this).is(":checked")) {
			$('<span id="real-time-udpate-warning-message">Warning: Real-time updates may incur high server loads.</span>').insertAfter($(this));
			var blockId = $(this).data('block-id');
			setInterval(function () {
				$.ajax({
					url: Theme_Variables.ajax_url,
					type: 'POST',
					data: {
						action: 'real_time_speaker_list',
						block_id: blockId,
						nonce: Theme_Variables.nonce
					},
					success: function (response) {
						$('#amsa-voting-speaker-list-wrapper').html(response.data['rendered_content']);
						console.log('refreshed');

					},
					error: function (response){
						console.log(response);
					}
				});
			}, 20000); // 20 seconds
		} else {
			// Clear interval if unchecked
			clearInterval();
			$('#real-time-udpate-warning-message').remove();
		}
	});
});
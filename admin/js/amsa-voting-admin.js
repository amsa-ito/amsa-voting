jQuery(document).ready(function($) {
	var spinner='<div class="loading-spinner" id="loading-spinnner"></div>';

    $('#download_amsa_rep_example_csv').click(function() {
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'post',
            data: {
                action: 'download_amsa_rep_example_csv' // AJAX action name
            },
            success: function(response) {
                // Trigger the download by redirecting to the data URL
                var blob = new Blob([response], { type: 'text/csv' });
                var link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = 'amsa_rep_example.csv';
                link.click();
            }
        });
    });

    $('#download_example_csv_proxy').click(function() {
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'post',
            data: {
                action: 'download_example_csv_proxy' // AJAX action name
            },
            success: function(response) {
                // Trigger the download by redirecting to the data URL
                var blob = new Blob([response], { type: 'text/csv' });
                var link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = 'proxy_example.csv';
                link.click();
            }
        });
    });

    $('#reset-amsa-proxy-principal-button').click(function() {
        var submitButton = $(this);
        var wrapper = $('#reset-amsa-proxy-principal-wrapper');

        submitButton.prop('disabled', true);
		wrapper.append(spinner);

        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'post',
            data: {
                action: 'reset_users_proxy_principal_meta' // AJAX action name
            },
            success: function(response) {
                alert("User proxy principal meta reset!");
            },
            error: function(response){
                alert("User User proxy principal meta reset failed!");
            }
        }).always(function(){
			submitButton.prop('disabled', false);
			wrapper.find('.loading-spinner').remove();
        });
    });

	$('#generate_amsa_rep_form').submit(function(e) {
        var wrapper = $('#generate_amsa_rep_form');
        var submitButton = $('submit_amsa_rep_csv');

        submitButton.prop('disabled', true);
		wrapper.append(spinner);

		e.preventDefault();
        var data = new FormData(this);
        data.append('csv_file', $('#amsa_rep_csv_file').prop('files')[0]);
        data.append('action', 'generate_amsa_reps');
        data.append('submit_csv', 1);
        data.append('send_invite', $('#send_invite').is(':checked'));
        
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'post',
            data: data,
			contentType: false,
			processData: false,
            success: function(response) {
                // Trigger the download by redirecting to the data URL
                alert('AMSA Reps susccessfully imported');
            },
            error: function (response) {
                console.log(response);
               }
			// error: function(response){
			// 	console.log(response);
			// }
        }).always(function(){
            submitButton.prop('disabled', false);
			wrapper.find('.loading-spinner').remove();
        });
    });

    $('#import_proxy_mapping_form').submit(function(e) {
        var wrapper = $('#import_proxy_mapping_form');
        var submitButton = $('submit_proxy_csv');

        submitButton.prop('disabled', true);
		wrapper.append(spinner);

		e.preventDefault();
        var data = new FormData(this);
        data.append('csv_file', $('#proxy_csv_file').prop('files')[0]);
        data.append('action', 'map_proxies_from_csv');
        data.append('submit_csv', 1);
        
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'post',
            data: data,
			contentType: false,
			processData: false,
            success: function(response) {
                // Trigger the download by redirecting to the data URL
                alert(response.data['alert_msg']);
                if(response.data['failed_emails'].length>0){
                    $('#proxy_mapping_failed_emails').append(response.data['failed_emails']).show();
                }
            },
            error: function (response) {
                console.log(response);
               }
			// error: function(response){
			// 	console.log(response);
			// }
        }).always(function(){
            submitButton.prop('disabled', false);
			wrapper.find('.loading-spinner').remove();
        });;
    });

    $('#institution_weighted').change(function(){
        var $weightedCheckbox = $('#institution_weighted');
        var $representativesCheckbox = $('#representatives_only');
        if ($weightedCheckbox.prop('checked')) {
            $representativesCheckbox.prop('checked', true);
            $representativesCheckbox.prop('disabled', true); // Optional: disable to enforce server-side logic
        } else {
            $representativesCheckbox.prop('disabled', false); // Enable when weighted is not checked
        }
    });
});
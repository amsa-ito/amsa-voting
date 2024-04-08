jQuery(document).ready(function($) {
    $('#download_example_csv').click(function() {
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'post',
            data: {
                action: 'download_example_csv' // AJAX action name
            },
            success: function(response) {
                // Trigger the download by redirecting to the data URL
                var blob = new Blob([response], { type: 'text/csv' });
                var link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = 'example.csv';
                link.click();
            }
        });
    });

    console.log('admin js loaded');
	$('#generate_amsa_rep_form').submit(function(e) {
		e.preventDefault();
        var data = new FormData(this);
        data.append('csv_file', $('#csv_file').prop('files')[0]);
        data.append('action', 'generate_amsa_reps');
        data.append('submit_csv', 1);
        
        console.log(data);
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'post',
            data: data,
			contentType: false,
			processData: false,
            success: function(response) {
                // Trigger the download by redirecting to the data URL
				console.log('it worked!');
                alert('AMSA Reps susccessfully imported');
            },
            error: function (response) {
                console.log(response);
               }
			// error: function(response){
			// 	console.log(response);
			// }
        });
    });
});
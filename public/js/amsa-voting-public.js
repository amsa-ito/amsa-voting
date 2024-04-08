jQuery(document).ready(function($) {

	$('#amsa-voting-dynamic-content-wrapper').on('click', '#submit_vote', function(event) {
		var postID = $('#amsa_voting_form').data('post_id');
		var vote = $('input[name="vote"]:checked').val();
		if (!vote) {
			alert('Please select an option.');
			return;
		}
		var data= {
			'action': 'process_and_store_votes',
			'post_id': postID,
			'vote': vote,
			'nonce': Theme_Variables.nonce
			// 'nonce': '<?php echo wp_create_nonce('ajax-voting-nonce'); ?>'
		};
		$.post(Theme_Variables.ajax_url, data, function(response){
			if (response.success){
                // Display a thank you message after the form
				$('#amsa-voting-dynamic-content-wrapper').html(response.data['rendered_content']);
				alert("Your vote is in!");
			} else{
				alert(response.data);
			}


		}).fail(function() {
            alert('There was a problem with your vote. Please try again.');
		});
	});
	$('#amsa-voting-dynamic-content-wrapper').on('click','#poll_status_change', function(event) {
		var postID = $('#admin-toggle-poll-status').data('post_id');
		var data= {
			'action': 'handle_poll_status_change',
			'post_id': postID,
			'poll_status_change':1,
			'nonce': Theme_Variables.nonce
		};
		
		$.post(Theme_Variables.ajax_url, data, function(response){
			if (response.success){
				$('#amsa-voting-dynamic-content-wrapper').html(response.data['rendered_content']);
				alert("Votes are now "+response.data['poll_status']);
				// location.reload(true);
			} else{
				alert(response.data);
			}


		}).fail(function() {
            alert('There was a problem with your vote. Please try again.');
		});
	});

	var timeout; // Variable to store the timeout

	// Function to run when #myInput changes

	
	$("#amsa-voting-proxy-table-wrapper").on("keyup", '#amsa-voting-search-proxy', function() {
		// Declare variables
		var input, filter, table, tr, td, i, txtValue;
		input = $("#amsa-voting-search-proxy");
		filter = input.val().toUpperCase();
		table = $("#amsa-voting-proxy-table");
		tr = table.find("tr");
		// Loop through all table rows, and hide those who don't match the search query
		tr.each(function() {
		  td1 = $(this).find("td").eq(0);
		  td2 = $(this).find("td").eq(1);
		  td3 = $(this).find("td").eq(2);
		  
		  if (td1 || td2 || td3) {
			txtValue = td1.text() || td1.html();
			txtValue += td2.text() || td2.html();
			txtValue += td3.text() || td3.html();

			if(txtValue){
				if (txtValue.toUpperCase().indexOf(filter) > -1) {
					$(this).show();
				  } else {
					$(this).hide();
				  }
			}

		  }
		});
	  }); 

	  $('#amsa-voting-proxy-table-wrapper').on('click', '.proxy-nominate-button', function() {
        var user_id = $(this).data('user-id');
		var post_id = $(this).data('post-id');
        var data = {
            'action': 'nominate_proxy',
            'proxy_user_id': user_id,
			'post_id': post_id,
			'nonce': Theme_Variables.nonce
        };
        $.post(Theme_Variables.ajax_url, data, function(response) {
            if (response.success) {
				$('#amsa-voting-proxy-nomination-header').html(response.data['rendered_content']);
				$('#amsa-voting-proxy-table-wrapper').hide();
                alert('User nominated as proxy successfully.');
                // You can add further actions here after successful nomination
            } else {
                alert(response);
				console.log(response);
            }
        });
    });

	$('#amsa-voting-proxy-nomination-header').on('click', '#retract-proxy-button', function() {
		var post_id = $(this).data('post-id');

        var data = {
            'action': 'retract_proxy',
			'nonce': Theme_Variables.nonce,
			'post_id': post_id
        };
        $.post(Theme_Variables.ajax_url, data, function(response) {
            if (response.success) {
				$('#amsa-voting-proxy-nomination-header').html(response.data['rendered_content']);
                alert("You've retracted your proxy");
                // You can add further actions here after successful nomination
            } else {
                alert('Failed to retract proxy.');
				console.log(response.data);
            }
        });
	});

	$('#amsa-voting-proxy-nomination-header').on('click', '#display-proxy-table-button', function() {
		var contentWrapper = $('#amsa-voting-proxy-table-wrapper');

		// Check if the content wrapper already has content
		if (contentWrapper.html().trim().length > 0) {
			// Content exists, so just toggle visibility
			contentWrapper.toggle();
		} else {
			var post_id = $(this).data('post-id');

			var data = {
				'action': 'diplay_proxy_table',
				'nonce': Theme_Variables.nonce,
				'post_id': post_id
			};
			$.post(Theme_Variables.ajax_url, data, function(response) {
				if (response.success) {
					contentWrapper.html(response.data['rendered_content']);
					// You can add further actions here after successful nomination
				} else {
					alert('Failed to retract proxy.');
					console.log(response.data);
				}
			});
		}
    });





});
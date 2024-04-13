jQuery(document).ready(function($) {
	var spinner='<div class="loading-spinner" id="loading-spinnner"></div>';

	function add_warning_message(message){
		$('#amsa-voting-poll-warning-messasge-text').text(message)
		$('#amsa-voting-poll-warning-messasges').show();
	}

	function scrollToElement(selector) {
		var $element = $(selector);
		if ($element.length) {
			 // Get the position of the element relative to the document
			 var elementOffset = $element.offset().top;

			 // Get the height of the element
			 var elementHeight = $element.outerHeight();
	 
			 // Get the height of the viewport
			 var viewportHeight = $(window).height();
	 
			 // Calculate the position to scroll to
			 // Center the element by subtracting half of the viewport height from the element's top offset and then adding half of the element's height
			 var scrollTopPosition = elementOffset - (viewportHeight / 2) + (elementHeight / 2);
	
			$('html, body').animate({
				scrollTop: scrollTopPosition
			}, 500); // 500 ms for animation speed
		}
	}

	$('#amsa-voting-dynamic-content-wrapper').on('click', '#submit_vote', function(event) {
		var postID = $('#amsa_voting_form').data('post_id');
		var vote = $('input[name="vote"]:checked').val();
		var submitButton = $('#submit_vote');
		var wrapper = $('#amsa-voting-dynamic-content-wrapper');
		
		submitButton.prop('disabled', true);
		wrapper.append(spinner);

		if (!vote) {
			$('#amsa-voting-poll-warning-messasge-text').prepend('Please select an option.');
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
				wrapper.html(response.data['rendered_content']);
				add_warning_message('Your vote is in!');

			} else{
				add_warning_message(response.data);
			}
		}).fail(function() {
            add_warning_message('There was a problem with your vote. Please try again.');
		}).always(function(){
			submitButton.prop('disabled', false);
			scrollToElement('#amsa-voting-poll-warning-messasge-text');
			wrapper.find('.loading-spinner').remove();
		});
	});

	$('#amsa-voting-dynamic-content-wrapper').on('click','#poll_status_change', function(event) {
		var postID = $('#admin-toggle-poll-status').data('post_id');
		var wrapper = $('#amsa-voting-dynamic-content-wrapper');
		var submitButton = $('#poll_status_change');

		submitButton.prop('disabled', true);
		wrapper.append(spinner);

		var data= {
			'action': 'handle_poll_status_change',
			'post_id': postID,
			'poll_status_change':1,
			'nonce': Theme_Variables.nonce
		};
		
		$.post(Theme_Variables.ajax_url, data, function(response){
			console.log(response.success);
			if (response.success){
				wrapper.html(response.data['rendered_content']);
				add_warning_message("Votes are now "+response.data['poll_status']);
				// location.reload(true);
			} else{
				console.log(response);
				add_warning_message("There was a problem with opening the poll");
			}
		}).fail(function() {
            add_warning_message('There was a problem with opening the poll. Please try again.');
		}).always(function(){
			submitButton.prop('disabled', false);
			wrapper.find('.loading-spinner').remove();
			scrollToElement('#amsa-voting-poll-warning-messasge-text');
		});
	});

	var timeout; // Variable to store the timeout

	// Function to run when #myInput changes

	
	$("#amsa-voting-proxy-nomination-list-wrapper").on("keyup", '#amsa-voting-search-proxy', function() {
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

	$('#amsa-voting-proxy-nomination-list-wrapper').on('click', '.proxy-nominate-button', function() {
		var submitButton = $(this);
		var wrapper = $('#amsa-voting-proxy-nomination-list-wrapper');

		submitButton.prop('disabled', true);
		wrapper.append(spinner);

		var user_id = submitButton.data('user-id');
		var post_id = submitButton.data('post-id');
        var data = {
            'action': 'nominate_proxy',
            'proxy_user_id': user_id,
			'post_id': post_id,
			'nonce': Theme_Variables.nonce
        };
        $.post(Theme_Variables.ajax_url, data, function(response) {
            if (response.success) {
				$('#amsa-voting-proxy-nomination-header').html(response.data['rendered_content']);
				wrapper.hide();
				$('#amsa-voting-dynamic-content-wrapper').html(response.data['voting_form']);
			scrollToElement('#amsa-voting-poll-warning-messasge-text');
			add_warning_message('User nominated as proxy successfully.');
                // You can add further actions here after successful nomination
            } else {
                add_warning_message("There was a problem nominating a proxy");
				console.log(response);
            }
		}).always(function(){
			submitButton.prop('disabled', false);
			wrapper.find('.loading-spinner').remove();
			scrollToElement('#amsa-voting-poll-warning-messasge-text');
        });
    });

	$('#amsa-voting-proxy-nomination-header').on('click', '#retract-proxy-button', function() {
		var submitButton = $(this);
		var wrapper = $('#amsa-voting-proxy-nomination-header');

		submitButton.prop('disabled', true);
		wrapper.append(spinner);

		var post_id = submitButton.data('post-id');

        var data = {
            'action': 'retract_proxy',
			'nonce': Theme_Variables.nonce,
			'post_id': post_id
        };
        $.post(Theme_Variables.ajax_url, data, function(response) {
            if (response.success) {
				wrapper.html(response.data['rendered_content']);
				$('#amsa-voting-dynamic-content-wrapper').html(response.data['voting_form']);
                add_warning_message("You've retracted your proxy");
                // You can add further actions here after successful nomination
            } else {
                add_warning_message('Failed to retract proxy.');
				console.log(response);
            }
		}).always(function(){
			submitButton.prop('disabled', false);
			wrapper.find('.loading-spinner').remove();
			scrollToElement('#amsa-voting-poll-warning-messasge-text');
        });
	});

	// show proxy nomination list
	$('#amsa-voting-proxy-nomination-header').on('click', '#display-proxy-table-button', function() {
		var submitButton = $(this);
		var wrapper = $('#amsa-voting-proxy-nomination-list-wrapper');
		var wrapperHtmlContent=wrapper.html().trim().length;

		// Check if the content wrapper already has content
		if (wrapperHtmlContent > 0) {
			// Content exists, so just toggle visibility
			wrapper.toggle();
			var display = wrapper.css('display');
			if(display==='none'){
				submitButton.text('Look for proxy');
			}else{
				submitButton.text('Hide proxy list');
			}
		} else {
			submitButton.prop('disabled', true);
			wrapper.append(spinner);

			var post_id = $(this).data('post-id');

			var data = {
				'action': 'diplay_proxy_table',
				'nonce': Theme_Variables.nonce,
				'post_id': post_id
			};
			$.post(Theme_Variables.ajax_url, data, function(response) {
				if (response.success) {
					wrapper.html(response.data['rendered_content']);
					// You can add further actions here after successful nomination
				} else {
					add_warning_message('Failed to display proxies.');
					console.log(response.data);
				}
			}).always(function(){
				submitButton.prop('disabled', false);
				submitButton.text('Hide proxy list');
				wrapper.find('.loading-spinner').remove();
				scrollToElement('#amsa-voting-poll-warning-messasge-text');
			});
		}
    });





});
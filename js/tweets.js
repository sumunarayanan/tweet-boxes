jQuery( window ).on(
	'load',
	function( $ ) {
		if ( jQuery( '.tweets-card' ).length == 0 ) {
			data = {
				'action' : 'render_tweets',
				'nonce': twitter_ajax.nonce
			};
			jQuery.ajax(
				{
					type: 'POST',
					dataType: 'json',
					url: twitter_ajax.ajaxurl,
					data: data,
					success: function( response ) {
						//template = wp.template( 'data-template' );
						if (response.status === 200) {
							jQuery( '#twitter-section' ).html( response.data );
						} else {
							console.log( response.message );
						}
					},
					error: function( requestObject, error, errorThrown ) {
						console.log( error );
						console.log( errorThrown );
					}
				}
			);
		}
	}
);

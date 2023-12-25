<?php
/**
* Plugin Name: Tweet Boxes
* Description: Get latest tweets
* Version: 1.1
* Author: Sumangala N
* License: GPL2
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
* Class Tweet_Box
*
* Class to authethicate and pull the latest tweets
*
* @since 1.0
*/
class Tweet_Box {
	const REQUIRED_TWEET_COUNT = 3;

	public function __construct() {
		add_shortcode( 'tp-tweet-boxes', array( $this, 'get_data_skeleton' ) );
		add_action( 'admin_menu', array( $this, 'add_twitter_admin_menu' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );
		add_action( 'wp_ajax_render_tweets', array( $this, 'render_tweets' ) );
		add_action( 'wp_ajax_nopriv_render_tweets', array( $this, 'render_tweets' ) );
	}

	public function enqueue_scripts_and_styles() {
		wp_enqueue_script( 'tp-twitter', plugin_dir_url( __FILE__ ) . 'js/tweets.js', array( 'jquery', 'wp-util' ), '1.0.0', true );

		$ajax_data = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ajax-nonce' ),
		);
		wp_localize_script( 'tp-twitter', 'twitter_ajax', $ajax_data );
		wp_enqueue_style( 'tp-twitter-style', plugin_dir_url( __FILE__ ) . 'css/style.css', array(), '1.0' );
	}

	public function add_twitter_admin_menu() {
		add_options_page( 'Twitter Options', 'Configure Twitter', 'manage_options', 'twitter', array( $this, 'add_twitter_admin_page' ) );
	}

	public function add_twitter_admin_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			update_option( 'TWITTER_CONSUMER_KEY', $_POST['consumer_key'] );
			update_option( 'TWITTER_CONSUMER_SECRET', $_POST['consumer_secret'] );
			update_option( 'TWITTER_SCREEN_NAME', $_POST['screen_name'] );
			$this->twitter_authenticate( true );
		}?>

		<div class="twitter-admin-options">
			<h1>Get Twitter Bearer Token</h1>
			<div class="message"></div>
			<form name="options" method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="screen_name">Screen Name (*)</label></th>
							<td> 
								<input type="text" id="screen_name" name="screen_name" value="<?php echo get_option( 'TWITTER_SCREEN_NAME', '' ); ?>" size="70">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="consumer_key">Consumer Key (*)</label></th>
							<td> 
								<input type="text" id="consumer_key" name="consumer_key" value="<?php echo get_option( 'TWITTER_CONSUMER_KEY', '' ); ?>" size="70">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="consumer_secret">Consumer Secret (*)</label></th>
							<td> 
								<input type="text" id="consumer_secret" name="consumer_secret" value="<?php echo get_option( 'TWITTER_CONSUMER_SECRET', '' ); ?>" size="70">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="bearer_token">Bearer Token</label></th>
							<td> 
								<input type="text" id="bearer_token" name="bearer_token"  disabled value="<?php echo get_option( 'TWITTER_BEARER_TOKEN', '' ); ?>" size="70">
							</td>
						</tr>

					</tbody>
				</table>
				<input type="hidden"  name="nonce" value="<?php echo esc_html( wp_create_nonce( 'settings-nonce' ) ); ?>" >
				<p class="submit">
					<input type="submit" name="submit" id="submit"  class="button button-primary" value="Save Changes">
				</p>
			</form>
		</div>
		<?php

	}

	public function twitter_authenticate( $force = false ) {
		$api_key    = get_option( 'TWITTER_CONSUMER_KEY' );
		$api_secret = get_option( 'TWITTER_CONSUMER_SECRET' );

		if ( $api_key && $api_secret && ( $force ) ) {
			$bearer_token_credential = $api_key . ':' . $api_secret;
			$credentials             = base64_encode( $bearer_token_credential );

			$args = array(
				'method'      => 'POST',
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => array(
					'Authorization' => 'Basic ' . $credentials,
					'Content-Type'  => 'application/x-www-form-urlencoded;charset=UTF-8',
				),
				'body'        => array( 'grant_type' => 'client_credentials' ),
			);

			/*  add_filter('https_ssl_verify', '__return_false'); */
			$response = wp_remote_post( 'https://api.twitter.com/oauth2/token', $args );
			$this->tweetKeys     = json_decode( $response['body'] );

			if ( !$this->tweetKeys ) {
				_e("<div class= 'notice notice-error'>
				<p>Failed to saved twitter token details</p>
				</div>");
				return false;
			}
			update_option( 'TWITTER_BEARER_TOKEN', $this->tweetKeys->{'access_token'} );
			_e("<div class= 'notice notice-success'>
				<p>Saved twitter token details</p>
				</div>");
		}
	}

	public function render_tweets() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
			$response = array( 'error' => 'Verification error!' );
			die( json_encode( $response ) );
		}

		try {
			$token       = get_option( 'TWITTER_BEARER_TOKEN' );
			$screen_name = get_option( 'TWITTER_SCREEN_NAME' );
			$user        = $this->get_user( $screen_name, $token );
			$user_details      = json_decode( $user, true );

			if ( empty( $user )|| empty( $user_details ) || empty(	$user_details['data']	) ) {
				$response = array(
					'status'  => 500,
					'message' => 'Failed to retrieve tweets',
				);
				die( json_encode( $response ) );
			}
			
			$user_id           = $user_details['data']['id'];
			$display_name      = $user_details['data']['name'];
			$profile_image_url = $user_details['data']['profile_image_url'];

			if ( $token && $user_id ) {
				$args = array(
					'httpversion' => '1.1',
					'blocking'    => true,
					'headers'     => array(
						'Authorization' => "Bearer $token",
					),
					'timeout'     => 30,
				);

				$api_url = "https://api.twitter.com/2/users/$user_id/tweets?max_results=5&tweet.fields=created_at,public_metrics,entities&expansions=referenced_tweets.id";

				$response = wp_remote_get( $api_url, $args );

				if ( is_wp_error( $response ) ) {
					$message = <<<EOT
					__METHOD__ ():__LINE__
					print_r( $handle, true )
					Aborting futher loading of tweets
					$response->get_error_message()
					EOT;

					$response = array(
						'status'  => 500,
						'message' => $message,
					);
					die( json_encode( $response ) );
				}

				$response_code    = wp_remote_retrieve_response_code( $response );
				$response_message = wp_remote_retrieve_response_message( $response );
				$response_body    = wp_remote_retrieve_body( $response );
				$tweets           = $this->parse_and_update_tweets( $response_body );
				$required_tweets  = array_slice( $tweets, 0, self::REQUIRED_TWEET_COUNT, true );

				if ( $response_code === 200 ) {
					$tweets_data  = array(
						'tweets'        => $required_tweets,
						'profile_image' => $profile_image_url,
						'display_name'  => $display_name,
						'screen_name'   => $screen_name,
					);
					$html_content = generate_tweet_cards( $tweets_data );
					$response = array(
						'status' => 200,
						'data'   => $html_content,
					);
					die( json_encode( $response ) );

				} else {
					$response = array(
						'status'  => 500,
						'message' => 'Failed to retrieve tweets',
					);
					die( json_encode( $response ) );
				}
			}
		} catch ( Exception $ex ) {
			$response = array(
				'status'  => 500,
				'message' => 'Failed to retrieve tweets',
			);
			die( json_encode( $response ) );
		}
	}
	/**
	 * Return the html content to set to a page for remote content
	 */
	public function get_data_skeleton() {
		return "<section id='twitter-section' class='container'></section>";
	}

	private function parse_and_update_tweets( $response ) {
		$arr_response = json_decode( $response, true );
		$tweets       = $arr_response['data'];
		$includes     = $arr_response['includes'];

		for ( $i = 0; $i < self::REQUIRED_TWEET_COUNT; $i++ ) {
			$tweet             = $tweets[ $i ];
			$text              = $tweet['text'];
			$formatted_date    = gmdate( 'j M Y', strtotime( $tweet['created_at'] ) );
			$entities          = $tweet['entities'];
			$referenced_tweets = isset( $tweet['referenced_tweets'] ) ? $tweet['referenced_tweets'] : null;
			$public_metrics    = null;
			$is_retweet        = false;

			foreach ( (array) $referenced_tweets as $referenced_tweet ) {
				if ( 'retweeted' === $referenced_tweet['type'] ) {
					$referenced_tweet_id = $referenced_tweet['id'];
					$index               = array_search( $referenced_tweet_id, array_column( $includes['tweets'], 'id' ) );
					$included_data       = $includes['tweets'][ $index ];
					$text                = $included_data['text'];
					$entities            = $included_data['entities'];
					$public_metrics      = $included_data['public_metrics'];
					$is_retweet          = true;
				}
			}

			foreach ( $entities as $type => $entity ) {
				if ( $type == 'urls' ) {
					foreach ( $entity as $j => $url ) {
						$update_with = "<a href='" . $url['url'] . "' target='_blank'" . " title= '" . $url['expanded_url'] . "'>" . $url['display_url'] . '</a>';
						$text        = str_replace( $url['url'], $update_with, $text );

					}
				} elseif ( $type == 'mentions' ) {
					foreach ( $entity as $j => $user ) {
						$update_with = "<a href='https://twitter.com/" . $user['username'] . "' target= '_blank' title='" . $user['username'] . "'>@" . $user['username'] . '</a>';
						$text        = str_replace( '@' . $user['username'], $update_with, $text );
					}
				}
			}
			$tweet['text']       = $text;
			$tweet['created_at'] = $formatted_date;
			if ( $is_retweet ) {
				$tweet['public_metrics'] = $public_metrics;
			}

			$tweets[ $i ] = $tweet;
		}
		return $tweets;
	}

	private function get_user( $handle, $token ) {
		$args = array(
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => "Bearer $token",
			),
		);
		$api_url  = "https://api.twitter.com/2/users/by/username/$handle?user.fields=profile_image_url";
		$response = wp_remote_get( $api_url, $args );
		if ( is_wp_error( $response ) ) {
			$message  = <<<EOT
			__METHOD__() __LINE__
			print_r( $handle, true )
			Aborting futher loading of tweets
			$response->get_error_message()
			EOT;
			error_log("Failed to retrieve tweets. More details: $message ");
			return null;
		}
		return ( isset( $response['body'] ) ) ? $response['body'] : null;

	}

	private function generate_tweet_cards( $tweets_data ) {
		$html_content = "<div class='row'> <div class='tweets-card'>";

		$display_name      = esc_html( $tweets_data['display_name'] );
		$screen_name       = esc_html( $tweets_data['screen_name'] );
		$profile_image_url = esc_html( $tweets_data['profile_image'] );

		foreach ( $tweets_data['tweets'] as $tweet ) {
			$created_date  = esc_html( $tweet['created_at'] );
			$text          = $tweet['text'];
			$id            = esc_html( $tweet['id'] );
			$reply_count   = esc_html( $tweet['public_metrics']['reply_count'] );
			$retweet_count = esc_html( $tweet['public_metrics']['retweet_count'] );
			$like_count    = esc_html( $tweet['public_metrics']['like_count'] );
			$quote_count   = esc_html( $tweet['public_metrics']['quote_count'] );

			/**
			 * retweet_count gives total retweets
			 * quote_count gives total retweets with comments
			 * To get total retweets count as shown in twitter
			 * add retweet_count + quote_count
			 */
			$retweet_count = $retweet_count + $quote_count;

			$html_content .= "<div class='card col-md-12 col-sm-12 col-4 offset-md-2 offset-lg-0 tweet-card'>
                            <div class='tweet-header'>
                            <a href='$screen_name target='_blank rel='noopener noreferrer'><img src=$profile_image_url alt='some-logo'></a>
                            <a href='https://twitter.com/$screen_name' target='_blank' rel='noopener noreferrer'>
                            <div class='tweet-header-content'>
                            <p class='tweet-display-name'>$display_name</p>
                            <p class='tweet-handler'>@$screen_name</p>
                            <p class='tweet-separator'><i class='fa fa-square'></i></p>
                            <p class='tweet-date'>$created_date</p>
                            </div></a></div><div class='tweet-text'><p> $text </p></div><div class='tweet-footer'>
                            <a href='https://www.twitter.com/$screen_name/status/$id' target='_blank rel='noopener noreferer'>";

			$html_content .= '<svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 13.5C19 14.0304 18.7893 14.5391 18.4142 14.9142C18.0391 15.2893 17.5304 15.5 17 15.5H5L1 19.5V3.5C1 2.96957 1.21071 2.46086 1.58579 2.08579C1.96086 1.71071 2.46957 1.5 3 1.5H17C17.5304 1.5 18.0391 1.71071 18.4142 2.08579C18.7893 2.46086 19 2.96957 19 3.5V13.5Z" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>';
			$html_content .= "<span>$reply_count</span></a>
                    <a href= 'https://www.twitter.com/$screen_name/status/$id' target='_blank' rel='noopener noreferer'>";

			$html_content .= '<svg width="21" height="17" viewBox="0 0 21 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14 13L17 16L20 13" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M10 1H13C14.0609 1 15.0783 1.42143 15.8284 2.17157C16.5786 2.92172 17 3.93913 17 5V14.5" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M7 4L4 1L1 4" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M11 16H8C6.93913 16 5.92172 15.5786 5.17157 14.8284C4.42143 14.0783 4 13.0609 4 12V2.5" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>';
			$html_content .= "<span>$retweet_count</span></a>
                    <a href= 'https://www.twitter.com/$screen_name/status/$id' target='_blank' rel='noopener noreferer'>";
			$html_content .= '<svg width="20" height="18" viewBox="0 0 20 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.612 2.88797C17.1722 2.44794 16.65 2.09888 16.0752 1.86073C15.5005 1.62258 14.8844 1.5 14.2623 1.5C13.6401 1.5 13.0241 1.62258 12.4493 1.86073C11.8746 2.09888 11.3524 2.44794 10.9126 2.88797L9.99977 3.80075L9.08699 2.88797C8.19858 1.99956 6.99364 1.50046 5.73725 1.50046C4.48085 1.50046 3.27591 1.99956 2.38751 2.88797C1.4991 3.77637 1 4.98131 1 6.23771C1 7.4941 1.4991 8.69904 2.38751 9.58745L3.30029 10.5002L9.99977 17.1997L16.6992 10.5002L17.612 9.58745C18.0521 9.14763 18.4011 8.62542 18.6393 8.05066C18.8774 7.4759 19 6.85985 19 6.23771C19 5.61556 18.8774 4.99951 18.6393 4.42475C18.4011 3.84999 18.0521 3.32779 17.612 2.88797V2.88797Z" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>';
			$html_content .= "<span>$like_count</span></a>
                    <a href= 'https://www.twitter.com/$screen_name/status/$id' target='blank' rel='noopener noreferer'>";
			$html_content .= '<svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 13.5V17.5C19 18.0304 18.7893 18.5391 18.4142 18.9142C18.0391 19.2893 17.5304 19.5 17 19.5H3C2.46957 19.5 1.96086 19.2893 1.58579 18.9142C1.21071 18.5391 1 18.0304 1 17.5V13.5" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M15 6.5L10 1.5L5 6.5" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M10 1.5V13.5" stroke="#5B7083" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>';
			$html_content .= '</a></div></div>';
		}
		$html_content .= '</div></div>';

		return $html_content;
	}
}
$tweet_box = new Tweet_Box();

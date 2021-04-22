<?php
	/**
	 * Plugin Name: MATM Google Reviews
	 * Version: 1.4
	 */

	require('vendor/autoload.php');
	require('post_types.php');
	require('settings_page.php');

	class matm_google_reviews {

		public $slug = "matm-google-reviews";
		public $settings;
		public $placesAPIBase = "https://maps.googleapis.com/maps/api/place/details/json";

		public function __construct() {
			$this->register_updater();
			$this->register_actions();
			$this->register_filters();
		}

		public function register_actions() {
			//Add Review post types
			add_action( 'init', 'matm_create_reviews_post_type', 0 );
			add_action( 'matm_get_google_reviews', [$this, 'get_google_reviews']);
			add_action( 'wp', 'schedule_check_for_reviews' );


			if ( is_admin() ) {
				$this->settings = new matm_google_reivews_settings();
			}
			add_action( 'admin_menu', array( $this->settings, 'google_reviews_add_plugin_page' ) );
			add_action( 'admin_init', array( $this->settings, 'google_reviews_page_init' ) );

			add_action("wp_ajax_matm_get_reviews", [$this, "ajax_get_google_reviews"]);
			add_action("wp_ajax_nopriv_matm_get_reviews", [$this, "ajax_get_google_reviews"]);
		}

		public function register_filters() {

		}

		/**
		 * register BitBucket updates
		 */
		public function register_updater() {

			$repo               = 'matmltd/matm-google-reviews-plugin';                 // name of your repository. This is either "<user>/<repo>" or "<team>/<repo>".
			$bitbucket_username = 'MatmWeb';   // your personal BitBucket username
			$bitbucket_app_pass = '9k2au3nC2PzSmEmNtQvP';   // the generated app password with read access

			new \Maneuver\BitbucketWpUpdater\PluginUpdater( __FILE__, $repo, $bitbucket_username, $bitbucket_app_pass );
		}

		public function ajax_get_google_reviews() {
			$this->get_google_reviews();
			wp_redirect(admin_url() . '/admin.php?page=google-reviews');
		}

		/**
		 * Check for latest reviews and add them to site
		 */
		public function get_google_reviews(  ) {
			$google_reviews_options = get_option( 'matm_google_reviews_options' ); // Array of All Options
			$google_maps_api_key = $google_reviews_options['google_maps_api_key']; // Google Maps API Key
			$google_maps_place_ids = explode(",", $google_reviews_options['google_maps_place_id']); // Google Maps Place IDs
			foreach($google_maps_place_ids as $google_maps_place_id) {
				$data = json_decode( file_get_contents( $this->placesAPIBase . "?placeid=" . $google_maps_place_id . "&key=" . $google_maps_api_key ) );
				if ( count( $data->result->reviews ?? [] > 0 ) ) {
					foreach ( $data->result->reviews as $review ) {
						$title = $this->get_review_title( $review );
						if ( ! $this->review_exists( $title ) ) {
							$this->add_new_review( $title, $review );
						}
					}
				}
			}
		}

		/**
		 * @param string $title
		 * @param object $review
		 *
		 * Add review to wordpress post
		 */
		public function add_new_review( string $title, $review ) {
			// Create post object
			$my_post = array(
				'post_title'    => wp_strip_all_tags( $title ),
				'post_content'  => $review->text,
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_type'     => 'review'
			);

// Insert the post into the database
			$postID = wp_insert_post( $my_post );
			update_post_meta($postID, 'review_score', $review->rating);
		}

		/**
		 * @param object $review
		 *
		 * @return string
		 *
		 * Generate review title from review object
		 */
		public function get_review_title($review): string {
			return $review->author_name . " - " . date('Y/m/d', $review->time);
		}

		/**
		 * @param $title string
		 *
		 * @return bool
		 *
		 * Check if review exists
		 */
		public function review_exists( string $title): bool {
			return (bool) get_page_by_title( $title, OBJECT, 'review' );
		}

		// Schedule Cron Job Event

		/**
		 * Schedule Cron to get new reviews every hour
		 */
		function schedule_check_for_reviews() {
			if ( ! wp_next_scheduled( 'get_google_reviews' ) ) {
				wp_schedule_event( time(), 'hourly', 'matm_get_google_reviews' );
			}
		}

	}
	$matm_google_reviews = new matm_google_reviews();
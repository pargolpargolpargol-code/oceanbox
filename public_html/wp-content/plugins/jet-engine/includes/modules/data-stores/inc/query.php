<?php
namespace Jet_Engine\Modules\Data_Stores;

class Query {

	public function __construct() {

		add_filter(
			'jet-engine/listing/grid/posts-query-args',
			array( $this, 'add_query_args' ), 10, 3
		);

		add_filter(
			'jet-engine/listing/grid/query-args',
			array( $this, 'add_front_store_query_args' ), 10, 4
		);

		add_action(
			'jet-engine/query-builder/query/after-query-setup',
			array( $this, 'setup_front_store_prop' )
		);

		// Make sure front-store post__in props will be merged correctly on load more
		add_action(
			'jet-engine/query-builder/listings/on-load-more-props-setup',
			array( $this, 'uset_front_store_post_in' )
		);
	}

	/**
	 * Remove front store post__in prop to make sure we correctly
	 * get Front-stored posts from the request.
	 *
	 * If we'll not remove this - we'll always get an empty results
	 * because by default post__in prop is set to config of front storage.
	 *
	 * @param object $query
	 */
	public function uset_front_store_post_in( $query ) {

		if ( ! empty( $query->final_query['post__in'] ) && is_array( $query->final_query['post__in'] ) ) {
			$has_front_store = false;

			foreach ( $query->final_query['post__in'] as $key => $value ) {
				if ( 'is-front' === $value ) {
					$has_front_store = true;
					break;
				}
			}

			if ( ! $has_front_store ) {
				return;
			}

			unset( $query->final_query['post__in'] );
		}
	}

	public function add_query_args( $args, $render, $settings ) {

		// phpcs:disable
		if ( jet_engine()->listings->is_listing_ajax() && ! empty( $_REQUEST['query'] ) ) {

			remove_filter(
				'jet-engine/listing/grid/posts-query-args',
				array( $this, 'add_query_args' ), 10
			);

			if ( ! empty( $_REQUEST['query']['is_front_store'] ) ) {

				$args = $_REQUEST['query'];

				if ( ! empty( $args['front_store__in'] ) ) {
					$args['post__in'] = $args['front_store__in'];
					// Required for security reasons to avoid unauthorized access to private posts data.
					$args['post_status'] = 'publish';
				} else {
					$args['post__in'] = array( PHP_INT_MAX );
				}

				add_filter(
					'jet-engine/listing/grid/add-query-data',
					array( $this, 'add_query_data_trigger' )
				);

				unset( $args['is_front_store'] );
			}

		// phpcs:enable
		} elseif ( ! empty( $settings['posts_query'] ) ) {

			$store = false;

			foreach ( $settings['posts_query'] as $query_item ) {
				if ( ! empty( $query_item['posts_from_data_store'] ) ) {
					$store = $query_item['posts_from_data_store'];
				}
			}

			if ( $store ) {

				$store_instance = Module::instance()->stores->get_store( $store );

				if ( $store_instance ) {
					if( $store_instance->get_type()->is_front_store() ) {
						$args['post__in'] = array(
							'is-front',
							$store_instance->get_type()->type_id(),
							$store_instance->get_slug(),
						);
					} else {
						$store_posts = $store_instance->get_store();

						if ( empty( $store_posts ) ) {
							$args['post__in'] = array( 'no-posts' );
						} else  {
							$args['post__in'] = $store_instance->get_store();
						}
					}
				}

				add_filter(
					'jet-engine/listing/grid/add-query-data',
					array( $this, 'add_query_data_trigger' )
				);
			}
		}

		return $args;
	}

	public function add_front_store_query_args( $args, $widget, $settings, $query ) {

		// phpcs:disable
		if ( jet_engine()->listings->is_listing_ajax() && ! empty( $_REQUEST['query'] ) ) {

			remove_filter(
				'jet-engine/listing/grid/query-args',
				array( $this, 'add_front_store_query_args' ), 10
			);

			$use_load_more = ! empty( $settings['use_load_more'] ) ? $settings['use_load_more'] : false;
			$use_load_more = filter_var( $use_load_more, FILTER_VALIDATE_BOOLEAN );

			if ( $use_load_more && ! empty( $_REQUEST['query']['post__in'] ) ) {
				$args['filtered_query']['post__in'] = $_REQUEST['query']['post__in'];
			}

			if ( ! empty( $_REQUEST['query']['is_front_store'] ) ) {

				if ( ! empty( $_REQUEST['query']['front_store__in'] ) ) {
					$args['filtered_query']['post__in'] = $_REQUEST['query']['front_store__in'];
					// Required for security reasons to avoid unathorized access to private posts data.
					$args['filtered_query']['post_status'] = 'publish';
				}

				add_filter(
					'jet-engine/listing/grid/add-query-data',
					array( $this, 'add_query_data_trigger' )
				);

				unset( $_REQUEST['query']['is_front_store'] );
			}

			return $args;
		}
		// phpcs:enable

		if ( empty( $query->current_wp_query ) && empty( $query->final_query ) ) {
			return $args;
		}

		$check_fields = array(
			'post__in',
		);

		$has_front_store = false;

		foreach ( $check_fields as $check_field ) {

			$value = null;

			if ( ! empty( $query->current_wp_query ) ) {
				$value = $query->current_wp_query->get( $check_field );
			} elseif ( $query->final_query ) {
				$value = isset( $query->final_query[ $check_field ] ) ? $query->final_query[ $check_field ] : null;
			}

			if ( empty( $value ) || ! is_array( $value ) ) {
				continue;
			}

			if ( isset( $value[0] ) && 'is-front' !== $value[0] ) {
				continue;
			}

			$args[ $check_field ] = $value;

			$has_front_store = true;
		}

		if ( $has_front_store ) {
			add_filter(
				'jet-engine/listing/grid/add-query-data',
				array( $this, 'add_query_data_trigger' )
			);
		}

		return $args;
	}

	public function setup_front_store_prop( $query ) {

		// phpcs:disable
		if ( ! jet_engine()->listings->is_listing_ajax() ) {
			return;
		}

		if ( empty( $_REQUEST['query'] ) ) {
			return;
		}

		if ( empty( $_REQUEST['query']['is_front_store'] ) ) {
			return;
		}

		if ( ! empty( $_REQUEST['query']['front_store__in'] ) ) {
			$query->final_query['post__in'] = $_REQUEST['query']['front_store__in'];
			// Required for security reasons to avoid unathorized access to private posts data.
			$query->final_query['post_status'] = 'publish';
		}
		// phpcs:enable
	}

	public function add_query_data_trigger( $res ) {

		$res = true;

		remove_filter(
			'jet-engine/listing/grid/add-query-data',
			array( $this, 'add_query_data_trigger' )
		);

		return $res;
	}
}

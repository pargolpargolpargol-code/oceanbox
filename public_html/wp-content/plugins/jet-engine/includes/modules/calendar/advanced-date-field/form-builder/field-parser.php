<?php
namespace Jet_Engine\Dynamic_Calendar\Advanced_Date_Field;

use JFB_Modules\Block_Parsers\Field_Data_Parser;
use JFB_Modules\Block_Parsers\Fields\Default_Parser;
use JFB_Modules\Block_Parsers\Interfaces\Multiple_Parsers;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Field_Parser extends Field_Data_Parser implements Multiple_Parsers {

	public function type() {
		return 'advanced-date-field';
	}

	public function generate_parsers(): \Generator {

		$raw_value = $this->get_context()->get_request( $this->name );
		$new_value = '';

		if ( is_string( $raw_value ) ) {
			$raw_value = json_decode( $raw_value, true );
		}

		$save_as     = ! empty( $this->settings['save_as'] ) ? $this->settings['save_as'] : 'timestamp';
		$date_format = false;
		$time_format = false;
		// Default value of this option is true, so we need to check exactly in this way
		$allow_time  = isset( $this->settings['allow_timepicker'] ) ? $this->settings['allow_timepicker'] : true;
		$allow_time  = filter_var( $allow_time, FILTER_VALIDATE_BOOLEAN );

		if ( 'timestamp' !== $save_as ) {
			$date_format = ! empty( $this->settings['date_format'] ) ? $this->settings['date_format'] : 'Y-m-d';
			$time_format = ! empty( $this->settings['time_format'] ) ? $this->settings['time_format'] : 'H:i:s';
		}

		$config_parser = new Default_Parser();
		$config_parser->set_context( $this->get_context() );
		$config_parser->set_type( $this->type() . '__config' );
		$config_parser->set_name( $this->name . '__config' );

		if ( ! empty( $raw_value['dates'] ) ) {
			$raw_value['dates'] = array_values( $raw_value['dates'] );
		}

		$config_parser->set_value( json_encode( $raw_value ) );

		$is_reccuring = ! empty( $raw_value['is_recurring'] ) ? $raw_value['is_recurring'] : false;
		$is_reccuring = filter_var( $is_reccuring, FILTER_VALIDATE_BOOLEAN );

		if ( ! class_exists( '\Jet_Engine_Advanced_Date_Recurring_Dates' ) ) {
			require_once jet_engine()->plugin_path( 'includes/modules/calendar/advanced-date-field/recurring-dates.php' );
		}

		$date_parts = array(
			$raw_value['date'],
			! empty( $raw_value['time'] ) ? $raw_value['time'] : '00:00:00',
		);

		$raw_value['initial_timestamp'] = strtotime( implode( ' ', $date_parts ) );

		if ( false !== $date_format ) {

			$raw_value['generated_date_format'] = $date_format;

			if ( $allow_time && false !== $time_format ) {
				$raw_value['generated_date_format'] .= ' ' . $time_format;
			}
		}

		$recurring_dates = new \Jet_Engine_Advanced_Date_Recurring_Dates( $raw_value );

		$rrule_parser = new Default_Parser();
		$rrule_parser->set_context( $this->get_context() );
		$rrule_parser->set_type( $this->type() . '__rrule' );
		$rrule_parser->set_name( $this->name . '__rrule' );
		$rrule_parser->set_value( $recurring_dates->generate_rrule() );

		if ( $is_reccuring && ! empty( $raw_value['date'] ) ) {
			// Set recurrency dates into the new value
			$new_value = $recurring_dates->with_end_dates( $recurring_dates->with_start_date(
				$recurring_dates->generate( true )
			) );
		} else {
			$new_value = $recurring_dates->extract_manual_dates();
		}

		$this->set_value( $new_value );
		$this->get_context()->update_request_value( $this->name, $new_value );

		yield $this;
		yield $config_parser;
		yield $rrule_parser;
	}

	/**
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}
}

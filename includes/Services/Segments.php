<?php
/**
 * Customer segmentation engine.
 *
 * @package GCRM\Services
 */

namespace GCRM\Services;

use GCRM\DB\Repositories\CartRepository;
use GCRM\DB\Repositories\GuestRepository;
use GCRM\DB\Repositories\SegmentRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Segments
 */
class Segments {

	/**
	 * Evaluate segment rules and return matching guest IDs.
	 *
	 * @param int $segment_id Segment ID.
	 * @return array<int>
	 */
	public function get_audience_ids( int $segment_id ): array {
		$repo    = new SegmentRepository();
		$segment = $repo->find( $segment_id );
		if ( ! $segment ) {
			return array();
		}
		$rules = json_decode( $segment['rules'] ?? '{}', true );
		if ( ! is_array( $rules ) ) {
			return array();
		}
		return $this->evaluate_rules( $rules );
	}

	/**
	 * Evaluate rules against all guests + carts.
	 *
	 * @param array<string, mixed> $rules Rules with logic AND/OR and conditions.
	 * @return array<int>
	 */
	public function evaluate_rules( array $rules ): array {
		$guest_repo = new GuestRepository();
		$all        = $guest_repo->query( array( 'per_page' => 99999 ) )['items'];
		$logic      = strtoupper( $rules['logic'] ?? 'AND' );
		$conditions = $rules['conditions'] ?? array();
		$ids        = array();

		foreach ( $all as $guest ) {
			$results = array();
			foreach ( $conditions as $cond ) {
				$results[] = $this->match_condition( $guest, $cond );
			}
			$match = 'OR' === $logic ? in_array( true, $results, true ) : ! in_array( false, $results, true );
			if ( $match && ! empty( $results ) ) {
				$ids[] = (int) $guest['id'];
			}
		}
		return $ids;
	}

	/**
	 * Match single condition.
	 *
	 * @param array<string, mixed> $guest Guest row.
	 * @param array<string, mixed> $cond Condition.
	 */
	private function match_condition( array $guest, array $cond ): bool {
		$field    = $cond['field'] ?? '';
		$operator = $cond['operator'] ?? 'eq';
		$value    = $cond['value'] ?? '';

		$guest_value = match ( $field ) {
			'order_count'          => (int) ( $guest['order_count'] ?? 0 ),
			'total_spend'          => (float) ( $guest['total_spend'] ?? 0 ),
			'country'              => (string) ( $guest['country'] ?? '' ),
			'city'                 => (string) ( $guest['city'] ?? '' ),
			'days_since_order'     => $this->days_since( $guest['last_order_date'] ?? '' ),
			'abandoned_cart_value' => $this->max_abandoned_value( $guest['email'] ?? '' ),
			default                => $guest[ $field ] ?? '',
		};

		return $this->compare( $guest_value, $operator, $value );
	}

	/**
	 * Compare values.
	 *
	 * @param mixed  $left Left value.
	 * @param string $operator Operator.
	 * @param mixed  $right Right value.
	 */
	private function compare( $left, string $operator, $right ): bool {
		$left  = is_numeric( $left ) ? (float) $left : $left;
		$right = is_numeric( $right ) ? (float) $right : $right;

		return match ( $operator ) {
			'eq'  => $left == $right,
			'neq' => $left != $right,
			'gt'  => $left > $right,
			'gte' => $left >= $right,
			'lt'  => $left < $right,
			'lte' => $left <= $right,
			'contains' => is_string( $left ) && str_contains( strtolower( $left ), strtolower( (string) $right ) ),
			default => false,
		};
	}

	/**
	 * Days since date.
	 *
	 * @param string $date Date string.
	 */
	private function days_since( string $date ): int {
		if ( ! $date ) {
			return 9999;
		}
		return (int) floor( ( time() - strtotime( $date ) ) / DAY_IN_SECONDS );
	}

	/**
	 * Max abandoned cart value for email.
	 *
	 * @param string $email Email.
	 */
	private function max_abandoned_value( string $email ): float {
		$carts = ( new CartRepository() )->query( array( 'search' => $email, 'status' => 'abandoned', 'per_page' => 100 ) );
		$max   = 0.0;
		foreach ( $carts['items'] as $cart ) {
			$max = max( $max, (float) ( $cart['cart_value'] ?? 0 ) );
		}
		return $max;
	}

	/**
	 * Preview count for rules JSON.
	 *
	 * @param array<string, mixed> $rules Rules.
	 */
	public function preview_count( array $rules ): int {
		return count( $this->evaluate_rules( $rules ) );
	}
}

<?php
/**
 * Integration smoke assertions, executed with WP CLI.
 *
 * @package WRDQ
 */
global $wpdb;
$documents = $wpdb->prefix . 'wrdq_documents';
$deliveries = $wpdb->prefix . 'wrdq_deliveries';
$count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$documents} WHERE event_key = %s AND status = %s",
		'ci-demo-001',
		'ready'
	)
);
if ( 1 !== $count ) {
	throw new RuntimeException( 'Expected one ready document, got ' . $count );
}
$delivery_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$deliveries} WHERE event_key = %s",
		'ci-demo-001'
	)
);
if ( 1 !== $delivery_count ) {
	throw new RuntimeException( 'Expected one delivery row, got ' . $delivery_count );
}
echo "WordPress integration smoke checks passed.\n";

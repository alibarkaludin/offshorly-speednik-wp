<?php
/**
 * Effective ad output.
 *
 * @var string $div_id              random alphanumeric ID of the div container.
 * @var string $path                ad unit path.
 * @var string $size                definition of ad unit size.
 * @var string $size_mapping_object definition of size mapping object.
 * @var string $size_mapping        string that adds the size mapping object to the ad call.
 * @var string $empty_div           optional string to allow collapsing the ad units.
 * @var string $key_values          optional key-values parameter.
 * @var int    $refresh             auto-refresh interval in seconds.
 */

if ( $refresh ) {
	$key_values .= ".setTargeting( 'gamrefresh', '" . $refresh . "' )";
}
?>
<script async="async" src="https://securepubads.g.doubleclick.net/tag/js/gpt.js"></script>
<script> var googletag = googletag || {}; googletag.cmd = googletag.cmd || [];</script>
<div id="<?php echo esc_attr( $div_id ); ?>">
  <script>
	googletag.cmd.push(function() {
		<?php
		echo $size_mapping_object;
		?>
		googletag.defineSlot( '<?php echo esc_attr( $path ); ?>', <?php echo $size; ?>, '<?php echo esc_attr( $div_id ); ?>' )
		.addService(googletag.pubads())<?php echo $key_values; ?><?php echo $size_mapping; ?><?php echo $empty_div; //phpcs:ignore ?>;
		<?php if ( $refresh ) : ?>
			if ( typeof window.advadsGamHasViewableListener === 'undefined' ) {
				googletag.pubads().addEventListener( 'impressionViewable', function ( event ) {
					const slot = event.slot, interval = slot.getTargeting( 'gamrefresh' );
					if ( interval.length ) {
						setInterval( function () {
							googletag.pubads().refresh( [slot] );
						}, parseInt( interval ) * 1000 );
					}
				} );
				window.advadsGamHasViewableListener = true;
			}
		<?php endif; ?>
		window.advadsGamEmptySlotsTimers = window.advadsGamEmptySlotsTimers || {};
		const timers                     = window.advadsGamEmptySlotsTimers;

		timers['<?php echo esc_js( $div_id ); ?>'] = setTimeout( function () {
			const id = '<?php echo esc_js( $div_id ); ?>';
			document.dispatchEvent( new CustomEvent( 'aagam_empty_slot', {detail: id} ) );
			delete ( timers[id] );
		}, 1000 );

		if ( typeof window.advadsGamHasEmptySlotListener === 'undefined' ) {
			googletag.pubads().addEventListener( 'slotRequested', function ( ev ) {
				const id = ev.slot.getSlotElementId();
				if ( typeof timers[id] === 'undefined' ) {
					return;
				}
				clearTimeout( timers[id] );
				timers[id] = setTimeout( function () {
					document.dispatchEvent( new CustomEvent( 'aagam_empty_slot', {detail: id} ) );
					delete ( timers[id] );
				}, 2500 );
			} );
			googletag.pubads().addEventListener( 'slotResponseReceived', function ( ev ) {
				const id = ev.slot.getSlotElementId();
				if ( typeof timers[id] !== 'undefined' ) {
					clearTimeout( timers[id] );
					delete ( timers[id] );
				}
				if ( ! ev.slot.getResponseInformation() ) {
					document.dispatchEvent( new CustomEvent( 'aagam_empty_slot', {detail: id} ) );
				}
			} );
			window.advadsGamHasEmptySlotListener = true;
		}

		googletag.enableServices();
		googletag.display( '<?php echo esc_attr( $div_id ); ?>' );
	} );
  </script>
</div>

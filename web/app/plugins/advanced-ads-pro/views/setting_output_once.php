<hr/>
<label for="advads-output-once" class="label"><?php _e( 'Display only once', 'advanced-ads-pro' ); ?></label>
<div>
<input id="advads-output-once" type="checkbox" name="advanced_ad[output][once_per_page]" value="1" <?php checked( $once_per_page, 1);  ?> />
<?php esc_html_e( 'Display the ad only once per page', 'advanced-ads-pro' ); ?>.
<a href="https://wpadvancedads.com/manual/optimizing-the-ad-layout/?utm_source=advanced-ads&utm_medium=link&utm_campaign=ad-edit-display-only-once#Display_only_once" target="_blank" class="advads-manual-link"><?php esc_html_e( 'Manual', 'advanced-ads' ); ?></a>
</div>

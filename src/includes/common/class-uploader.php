<?php
namespace WallLib\common;
//namespace Dev\UM_Activity\WallLib\common;
//namespace Dev\UM_Groups\WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Uploader
 *
 * @package WallLib\common
 */
class Uploader {

	private $wall;

	/**
	 * Uploader constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;

		// Change uploader placeholder
		add_filter( 'um_upload_item_placeholder', array( $this, 'list_item_placeholder' ), 10, 2 );
		add_filter( 'um_upload_edit_list_item_row', array( $this, 'edit_list_item_row' ), 10, 3 );
	}

	/**
	 * Gets post permalink
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_permalink( $post_id ) {
		$url = apply_filters( $this->wall->prefix . 'wall_get_core_page', '' );
		return add_query_arg( 'wall_post', $post_id, $url );
	}

	/**
	 * @param $value
	 * @param $args
	 *
	 * @return false|string
	 */
	public function list_item_placeholder( $value, $args ) {
		$handler_name = str_replace( 'um-', '', $this->wall->textdomain );
		if ( ! isset( $args['handler'] ) || $handler_name . '-post-photo' !== $args['handler'] ) {
			return $value;
		}

		ob_start();
		?>
		<div class="um-uploader-file-placeholder um-uploader-image-file-placeholder um-display-none">
			<div class="um-uploader-file-data">
				<div class="um-uploader-file-preview-error um-display-none"></div>
				<div class="um-uploader-wall-photos-data-wrapper">
					<div class="um-uploader-file-preview" title="{{{name}}}"></div>
					<div class="um-uploader-file-data-header">
						<div class="um-uploader-file-data-header-info">
							<div class="um-uploader-file-name">{{{name}}}</div>
							<?php
							$button_content = UM()->frontend()::layouts()::svg( 'trash' );
							$button_args    = array(
								'type'          => 'button',
								'icon_position' => 'content',
								'design'        => 'link-gray',
								'size'          => 's',
								'classes'       => array( 'um-uploader-file-remove' ),
							);
							echo wp_kses( UM()->frontend()::layouts()::button( $button_content, $button_args ), UM()->get_allowed_html( 'templates' ) );
							?>
						</div>
						<div class="um-supporting-text">{{{supporting}}}</div>
						<?php echo wp_kses( UM()->frontend()::layouts()::progress_bar( array( 'label' => 'right' ) ), UM()->get_allowed_html( 'templates' ) ); ?>
					</div>
				</div>
				<?php
				if ( true !== $args['async'] ) {
					$name_loaded_name = $args['multiple'] ? $args['name'] . '[{{{file_id}}}][name_loaded]' : $args['name'] . '[name_loaded]';
					$filename_name    = $args['multiple'] ? $args['name'] . '[{{{file_id}}}][filename]' : $args['name'] . '[filename]';
					$hash_name        = $args['multiple'] ? $args['name'] . '[{{{file_id}}}][hash]' : $args['name'] . '[hash]';
					?>
					<input type="hidden" class="um-uploaded-filename" data-field="<?php echo esc_attr( $args['field_id'] ); ?>" name="<?php echo esc_attr( $filename_name ); ?>" value="" disabled />
					<input type="hidden" class="um-uploaded-value-hash" name="<?php echo esc_attr( $hash_name ); ?>" value="" disabled />
					<input type="hidden" class="um-uploaded-name-loaded" name="<?php echo esc_attr( $name_loaded_name ); ?>" value="" disabled />
					<?php
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param bool|string $value
	 * @param array       $args
	 * @param mixed       $edit_value_row
	 *
	 * @return false|string
	 */
	public function edit_list_item_row( $value, $args, $edit_value_row ) {
		$handler_name = str_replace( 'um-', '', $this->wall->textdomain );
		if ( ! isset( $args['handler'] ) || $handler_name . '-post-photo' !== $args['handler'] ) {
			return $value;
		}

		$photo_id       = $edit_value_row['photo_id'];
		$preview_url    = $edit_value_row['preview_url'];
		$image_filename = $edit_value_row['filename'];

		$meta = wp_get_attachment_metadata( $photo_id );
		$size = '';
		if ( is_array( $meta ) && ! empty( $meta['filesize'] ) ) {
			$bytes = absint( $meta['filesize'] );

			if ( $bytes <= 0 ) {
				$size = '0 kb';
			} else {
				$kb = $bytes / 1024;

				// KB — до целого
				if ( $kb < 1024 ) {
					$size = round( $kb ) . ' kb';
				} else {
					// MB — до 1 знака
					$mb   = $kb / 1024;
					$size = number_format_i18n( $mb, 1 ) . ' mb';
				}
			}
		}

		ob_start();
		?>
		<div class="um-uploader-file" id="wall-photo-<?php echo esc_attr( $photo_id ); ?>">
			<div class="um-uploader-file-data">
				<div class="um-uploader-file-preview-error um-display-none"></div>
				<div class="um-uploader-wall-photos-data-wrapper">
					<div class="um-uploader-file-preview" title="<?php echo esc_attr( $image_filename ); ?>">
						<img src="<?php echo esc_url( $preview_url ); ?>" alt="<?php echo esc_attr( $image_filename ); ?>" />
					</div>
					<div class="um-uploader-file-data-header">

						<div class="um-uploader-file-name"><?php echo esc_attr( $image_filename ); ?></div>
						<?php
						$button_content = UM()->frontend()::layouts()::svg( 'trash' );
						echo wp_kses(
							UM()->frontend()::layouts()::button(
								'',
								array(
									'type'          => 'button',
									'design'        => 'link-gray',
									'size'          => 's',
									'icon_position' => 'content',
									'icon'          => $button_content,
									'title'         => __( 'Delete photo', 'um-user-photos' ),
									'classes'       => array( 'um-wall-delete-photo', 'um-uploader-file-remove' ),
									'data'          => array(
										'id'           => $photo_id,
										'nonce'        => wp_create_nonce( 'um_wall_delete_photo' . $photo_id ),
										'confirmation' => __( 'Are you sure you want to delete this photo?', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
										'error'        => __( 'Something went wrong', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
									),
								)
							),
							UM()->get_allowed_html( 'templates' )
						);
						?>

					</div>
					<div class="um-supporting-text"><?php echo esc_html( $size ); ?></div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

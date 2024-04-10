<?php
/**
 * Wicket Callout Block
 *
 **/

namespace Wicket_AC\Blocks\AC_Callout_Block;

function init( $block = [] ) { 

	$attrs = get_block_wrapper_attributes();
	$block_logic = get_field('block_logic');
	$renewal_period = get_field('renewal_period');
	$memberships = wicket_get_active_memberships();
	switch($block_logic){

		case 'become_member': 
			$show_block = (!$memberships) ? true : false;
			break;

		case 'renewal': 
			$membership_to_renew = is_renewal_period($memberships, $renewal_period);
			$show_block = ($membership_to_renew) ? true : false;
			break;
		
		case 'profile': 
			break;
		
	}
	

	$my_block_template = array(
			array(
					'core/group',
					array(
							'layout' => array(
									'type' => 'constrained',
							),
					),
					array(
							array(
								'core/heading',
								array(
										'level'		=> '2',
										'align'   => 'left',
										'placeholder' => 'This is a block title',
								),
								array(),
							),
							array(
									'core/paragraph',
									array(
											'align'   => 'left',
											'placeholder' => 'Paragraph content.',
									),
									array(),
							),
							array(
									'core/button',
									array(
											'align'   => 'left',
											'url'   => '#',
											'placeholder' => 'Button Label',
									),
									array(),
							),
					),
			),
	);
	

	$allowed_blocks = array(
		'core/heading', 
		'core/paragraph',
		'core/button'
	);
	?>

		<?php
		// Show the block if conditional logic is true OR if viewing in the block editor
		if($show_block || is_admin()): ?>

		<div <?php echo $attrs; ?>>
			<InnerBlocks allowedBlocks="<?php echo esc_attr( wp_json_encode( $allowed_blocks ) ); ?>" template="<?php echo esc_attr( wp_json_encode( $my_block_template ) ); ?>" />
		</div>

		<?php endif; ?>
	
	<?php
}
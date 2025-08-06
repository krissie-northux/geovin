<?php
/**
* Gravity Wiz // Gravity Forms // Disable Submit Button Until Required Fields are Field Out
*
* Disable submit buttones until all required fields have been filled out. Currently only supports single-page forms.
*
* @version   1.0
* @author    David Smith <david@gravitywiz.com>
* @license   GPL-2.0+
* @link      http://gravitywiz.com/...
* @copyright 2013 Gravity Wiz
*/
class GW_Disable_Submit {

	public static $script_output = false;

	public function __construct( $form_id ) {

		add_action( "gform_pre_render_{$form_id}", array( $this, 'maybe_output_script' ) );
		add_action( "gform_register_init_scripts_{$form_id}", array( $this, 'add_init_script' ) );

	}

	/**
	 * Check if the script has already been output for this form.
	 * If not, output the script to handle disabling the submit button.
	 *
	 * @param array $form The form object.
	 * @return array The form object with the script added.
	 */
	public function maybe_output_script( $form ) {

		if ( ! self::$script_output ) {
			$this->script();
			self::$script_output = true;
		}

		return $form;
	}

	/**
	 * Output the script that will handle disabling the submit button
	 * until all required fields are filled out.
	 *
	 * This script is added to the footer of the form page.
	 *
	 * @return void
	 */
	public function script() {
		?>

		<script type="text/javascript">

			var GWDisableSubmit;

				GWDisableSubmit = function( args ) {

					var self = this;

					// copy all args to current object: formId, fieldId
					for( prop in args ) {
						if( args.hasOwnProperty( prop ) )
							self[prop] = args[prop];
					}

					self.init = function() {
						$(document).change( args.inputHtmlIds.join( ', ' ) , function() {
							console.log('running self check');
							self.runCheck();
						} );

						self.runCheck();

						gform.addAction( 'gform_list_post_item_add', function ( item, container ) {
							self.runCheck();
						})

						gform.addAction( 'gform_list_post_item_delete', function ( container ) {
						    self.runCheck();
						} );

					}

					self.runCheck = function() {
						var submitButton = $( '#gform_submit_button_' + self.formId );

						if( self.areRequiredPopulated() ) {
							submitButton.attr( 'disabled', false ).removeClass( 'gwds-disabled' );
						} else {
							submitButton.attr( 'disabled', true ).addClass( 'gwds-disabled' );
						}

					}

					self.areRequiredPopulated = function() {
						var inputs     = $( args.inputHtmlIds.join( ', ' ) ),
							inputCount = inputs.length,
							fullCount  = 0;

						$( inputs ).each( function() {
							var input   = $( this ),
								fieldId = input.attr( 'id' ) ? input.attr( 'id' ).split( '_' )[2] : input.attr('name').split('_')[1].replace('[]','');
							// don't count fields hidden via conditional logic towards the inputCount
							
							if ( $(input).parents('fieldset').is(':hidden') ) {
								inputCount -= 1;
								return;
							} else if( window['gf_check_field_rule'] && gf_check_field_rule( self.formId, fieldId, null, null ) == 'hide' ) {
								inputCount -= 1;
								return;
							}
							if ( $( this ).hasClass('invalid') ) {
								return false;
							} else if( ! $( this ).is(':checkbox') && $.trim( $( this ).val() ) ) {
								fullCount += 1;
							} else if ( $( this ).is(':checkbox') && $( this ).prop('checked') ) {
								fullCount += 1;
							} else {
								return false;
							}
						} );
						
						return fullCount == inputCount;
					}

					self.init();

				}

		</script>

		<?php
	}

	/**
	 * Add the initialization script to the form.
	 * This script will run when the form is rendered and will handle disabling the submit button.
	 *
	 * @param array $form The form object.
	 */
	public function add_init_script( $form ) {

		$args = array(
			'formId'       => $form['id'],
			'inputHtmlIds' => $this->get_required_input_html_ids( $form ),
		);

		$script = '; new GWDisableSubmit( ' . json_encode( $args ) . ' );';
		$slug   = "gw_disable_submit_{$form['id']}";

		GFFormDisplay::add_init_script( $form['id'], $slug, GFFormDisplay::ON_PAGE_RENDER, $script );

	}

	/**
	 * Get the HTML IDs of all required inputs in the form.
	 * This will be used to determine which inputs to check for required fields.
	 *
	 * @param array $form The form object.
	 * @return array An array of HTML IDs for required inputs.
	 */
	public function get_required_input_html_ids( $form ) {

		$html_ids = array();

		foreach ( $form['fields'] as &$field ) {

			if ( ! $field['isRequired'] ) {
				continue;
			}

			$input_ids = false;

			switch ( GFFormsModel::get_input_type( $field ) ) {

				case 'address':
					$input_ids = array( 1, 3, 4, 5, 6 );
					break;

				case 'name':
					$input_ids = array( 1, 2, 3, 5, 6 ); //removed middle name from required by leaving out 4
					break;

				case 'list':
					$input_count = count($field->choices);
					$inputs_added = 0;
					do {
						$inputs_added++;
						$html_ids[] = ".gfield_list_{$field['id']}_cell{$inputs_added} input";

					} while ( $inputs_added < $input_count );
					break;

				case 'consent':
					$input_ids = array( 1, 2, 3 );
					break;

				case 'chainedselect':
					$input_ids = array( 1, 2 );
					break;

				default:
					$html_ids[] = "#input_{$form['id']}_{$field['id']}";
					break;

			}

			if ( ! $input_ids ) {
				continue;
			}

			foreach ( $input_ids as $input_id ) {
				$html_ids[] = "#input_{$form['id']}_{$field['id']}_{$input_id}";
			}
		}

		return $html_ids;
	}

}

# Configuration

//new GW_Disable_Submit( $form_id );
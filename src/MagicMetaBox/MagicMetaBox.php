<?php
/**
 * @version 1.1.0
 * @author R4c00n <marcel.kempf93@gmail.com>
 * @licence MIT
 */
namespace MagicMetaBox;

/**
 * Helper class to generate wordpress metaboxes
 *
 * @since 1.0.0
 * @author R4c00n <marcel.kempf93@gmail.com>
 * @licence MIT
 */
class MagicMetaBox {

    /**
     * @since 1.0.0
     * @access protected
     * @var string
     */
    protected $id = '';

    /**
     * @since 1.0.0
     * @access protected
     * @var string
     */
    protected $title = '';

    /**
     * @since 1.0.0
     * @access protected
     * @var array
     */
    protected $screens = array();

    /**
     * @since 1.0.0
     * @access protected
     * @var string
     */
    protected $prefix = '';

    /**
     * @since 1.1.0
     * @access protected
     * @var string
     */
    protected $context = '';

    /**
     * @since 1.1.0
     * @access protected
     * @var string
     */
    protected $priority = '';

    /**
     * @since 1.0.0
     * @access protected
     * @var string
     */
    protected $metaName = '';

    /**
     * @since 1.0.0
     * @access private
     * @var array
     */
    private $fields = array();

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $id
     * @param string $title
     * @param array $screens
     * @param string $prefix
     * @param string $context
     * @param string $priority
     */
    public function __construct( $id, $title, $screens, $prefix, $context = 'advanced',
                                 $priority = 'default', $serialize = true ) {
        $this->id = $id;
        $this->title = $title;
        $this->screens = $screens;
        $this->prefix = $prefix;
        $this->metaName = $this->prefix . $this->id;
        $this->context = $context;
        $this->priority = $priority;
        $this->serialize = $serialize;

        add_action( 'add_meta_boxes', array( $this, 'addMetaBox' ) );
        add_action( 'save_post', array( $this, 'saveMetaBox' ), 10, 3 );
    }

    /**
     * Add the meta box.
     *
     * @since 1.0.0
     * @return void
     */
    public function addMetaBox() {
        foreach ( $this->screens as $screen ) {
            add_meta_box(
                $this->id,
                $this->title,
                array( $this, 'metaBoxCallback' ),
                $screen,
                $this->context,
                $this->priority
            );
        }
    }

    /**
     * Add the meta box.
     *
     * @since 1.0.0
     * @param \WP_Post $post
     * @return void
     */
    public function metaBoxCallback( \WP_Post $post ) {
        ?>
        <table class="form-table">
            <tbody>
            <?php foreach ( $this->fields as $field ): ?>
                <tr>
                    <?php if ( !empty( $field['label'] ) ): ?>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $field['name'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                        </th>
                    <?php endif; ?>
                    <td>
                        <?php
                        if ( $this->serialize) {
                            $meta = get_post_meta( $post->ID, $this->prefix . $this->id, true );
                        } else {
                            $meta = [];
                            $meta[$field['name']] = get_post_meta( $post->ID, $field['name'], true );
                        }
                        $methodName = 'show' . ucfirst( $field['type'] ) . 'Field';
                        if ( method_exists( $this, $methodName ) ) {
                            call_user_func( array( $this, $methodName ), $field, $meta );
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Save the meta box.
     *
     * @since 1.0.0
     * @param int $postId
     * @param post $post The post object.
     * @param bool $update Whether this is an existing post being updated or not.
     * @return void
     */
    public function saveMetaBox( $postId, $post, $update ) {
        $isAutoSave = wp_is_post_autosave( $postId );
        $isRevision = wp_is_post_revision( $postId );
        $isValidNonce = ( isset( $_POST[$this->metaName . '_nonce'] )
            && wp_verify_nonce( $_POST[$this->metaName . '_nonce'], basename( __FILE__ ) ) ) ? true : false;
        if ( $isAutoSave || $isRevision || $isValidNonce ) {
            return;
        }

        foreach ( $this->fields as $field ) {
            $metaName = $field['name'];
            $single = !isset( $field['multiple'] ) || !$field['multiple'] ? true : false;
            $oldMeta = get_post_meta( $postId, $this->metaName, true );
            if ( empty( $oldMeta ) ) {
                $oldMeta = $single ? '' : array();
            }

            $postMeta = filter_input( INPUT_POST, $this->metaName, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
            $newMetaValue = isset( $postMeta[$metaName] ) ? $postMeta[$metaName] : ( $single ? '' : array() );
            
            $this->saveField( $postId, $field, $oldMeta, $newMetaValue, $update );
        }
    }

    /**
     * Save a field.
     *
     * @since 1.0.0
     * @param int $postId
     * @param array $field
     * @param string|array $oldMeta
     * @param string|array $newMetaValue
     * @param bool $update
     * @return void
     */
    protected function saveField( $postId, $field, $oldMeta, $newMetaValue, $update ) {
        $metaName = $field['name'];
        $metaKey = !$this->serialize ? $metaName : $this->metaName;
        
        if ( !$this->serialize ) {
            // Pevent adding default value if post is new or the new meta value is
            // equivalent to the default
            if( !$field['save_default'] ) {
                $eql = $newMetaValue === $field['default'];
                $eql_strings = strval( $newMetaValue ) === strval( $field['default'] );
                $numeric_types = is_numeric( $newMetaValue ) && is_numeric( $field['default'] );
                $key_isset = array_key_exists( $field['name'], (array)$_POST[$this->metaName] );
                
                if( $eql || ( $numeric_types && $eql_strings ) || ( !$update && !$key_isset ) ) {
                    delete_post_meta( $postId, $metaKey );
                    return;
                }
            }
            
            update_post_meta( $postId, $metaKey, $newMetaValue );
            
            return;
        }
        
        die(3);
        
        if ( isset( $oldMeta[$metaName] ) ) {
            unset( $oldMeta[$metaName] );
            update_post_meta( $postId, $metaKey, $oldMeta );
        }
        if ( !is_array( $newMetaValue ) ) {
            $newMetaValue = trim( $newMetaValue );
        }
        $oldMeta[$metaName] = $newMetaValue;
        
        update_post_meta( $postId, $metaKey, $oldMeta );
    }

    /**
     * Add a text field.
     *
     * @since 1.0.0
     * @param string $name
     * @param array $attributes
     * @param string $label
     * @param string $default
     * @return void
     */
    public function addTextField( $name, $attributes = array(), $label = '', $default = '' ) {
        $this->fields[] = array(
            'type' => 'text',
            'name' => $name,
            'attributes' => $attributes,
            'label' => $label,
            'default' => $default,
        );
    }

    /**
     * Add a text area field.
     *
     * @since 1.0.0
     * @param string $name
     * @param array $attributes
     * @param string $label
     * @param string $default
     * @return void
     */
    public function addTextAreaField( $name, $attributes = array(), $label = '', $default = '' ) {
        $this->fields[] = array(
            'type' => 'textArea',
            'name' => $name,
            'attributes' => $attributes,
            'label' => $label,
            'default' => $default,
        );
    }

    /**
     * Add a select field.
     *
     * @since 1.0.0
     * @param string $name
     * @param array $options
     * @param bool $multiple
     * @param array $attributes
     * @param string $label
     * @param bool $save_default Whether to save value when it equals the default (first value in the options list)
     * @return void
     */
    public function addSelectField( $name, $options, $multiple, $attributes = array(), $label = '', $save_default = true ) {
        if( !is_array( $options ) ) {
            $options = array();
        } else {
            reset( $options );
        }
        
        $this->fields[] = array(
            'type' => 'select',
            'name' => $name,
            'options' => $options,
            'multiple' => $multiple,
            'attributes' => $attributes,
            'label' => $label,
            'default' => count( $options ) ? key( $options ) : '',
            'save_default' => $save_default
        );
    }

    /**
     * Add a checkbox field.
     *
     * @since 1.0.0
     * @param string $name
     * @param array $attributes
     * @param string $label
     * @return void
     */
    public function addCheckboxField( $name, $attributes = array(), $label = '' ) {
        $this->fields[] = array(
            'type' => 'checkbox',
            'name' => $name,
            'attributes' => $attributes,
            'label' => $label,
        );
    }

    /**
     * Show text field.
     *
     * @since 1.0.0
     * @param array $field
     * @param string|array $meta
     * @return void
     */
    protected function showTextField( $field, $meta ) {
        $value = isset( $meta[$field['name']] ) ? esc_attr( $meta[$field['name']] ) : '';
        ?>
        <input id="<?php echo esc_attr( $field['name'] ); ?>"
            type="text"
            name="<?php echo esc_attr( $this->metaName ); ?>[<?php echo esc_attr( $field['name'] ); ?>]"
            value="<?php echo $value ? $value : esc_attr( $field['default'] ); ?>"
            <?php $this->generateElementAttributes( $field['attributes'] ); ?>/>
        <?php
    }

    /**
     * Show select field.
     *
     * @since 1.0.0
     * @param array $field
     * @param string|array $meta
     * @return void
     */
    protected function showSelectField( $field, $meta ) {
        $value = isset( $meta[$field['name']] ) ? esc_attr( $meta[$field['name']] ) : '';
        $multiple = isset( $field['multiple'] ) && $field['multiple'] ? true : false;
        $name = $this->metaName . '[' . $field['name'] . ']';
        if ( $multiple ) {
            $name .= '[]';
        }
        ?>
        <select id="<?php echo esc_attr( $field['name'] ); ?>"
            name="<?php echo esc_attr( $name ); ?>"
            value="<?php echo $value; ?>"
            <?php echo $multiple ? 'multiple="multiple"' : ''; ?>
            <?php $this->generateElementAttributes( $field['attributes'] ); ?>>
            <?php foreach ( $field['options'] as $key => $option ) : ?>
                <?php
                if ( !is_array( $value ) ) {
                    if(is_numeric($key) && is_numeric($value)) {
                        $selected = strval($value) === strval($key) ? 'selected' : '';
                    } else {
                        $selected = $value === $key ? 'selected' : '';
                    }
                } else {
                    $selected = in_array( $key, $value ) ? 'selected' : '';
                }
                ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php echo $selected; ?>>
                    <?php echo esc_html( $option ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Show text field.
     *
     * @since 1.0.0
     * @param array $field
     * @param string|array $meta
     * @return void
     */
    protected function showTextAreaField( $field, $meta ) {
        $value = isset( $meta[$field['name']] ) ? esc_attr( $meta[$field['name']] ) : '';
        ?>
        <textarea id="<?php echo esc_attr( $field['name'] ); ?>"
            name="<?php echo esc_attr( $this->metaName ); ?>[<?php echo esc_attr( $field['name'] ); ?>]"
            <?php $this->generateElementAttributes( $field['attributes'] ); ?>>
            <?php echo $value ? $value : esc_attr( $field['default'] ); ?>
        </textarea>
        <?php
    }

    /**
     * Show checkbox field.
     *
     * @since 1.0.0
     * @param array $field
     * @param string|array $meta
     * @return void
     */
    protected function showCheckboxField( $field, $meta ) {
        $value = isset( $meta[$field['name']] ) ? esc_attr( $meta[$field['name']] ) : '';
        $checked = $value === 'on' ? 'checked' : '';
        ?>
        <input id="<?php echo esc_attr( $field['name'] ); ?>"
            type="checkbox"
            name="<?php echo esc_attr( $this->metaName ); ?>[<?php echo esc_attr( $field['name'] ); ?>]"
            <?php echo $checked; ?>
            <?php $this->generateElementAttributes( $field['attributes'] ); ?>/>
        <?php
    }

    /**
     * Output element attributes.
     *
     * @since 1.0.0
     * @param array $attributes
     * @return void
     */
    protected function generateElementAttributes( $attributes ) {
        $elementAttributes = '';
        foreach ( $attributes as $key => $value ) {
            $elementAttributes .= $key . '="' . esc_attr( $value ) . '"';
        }
        echo $elementAttributes;
    }
}

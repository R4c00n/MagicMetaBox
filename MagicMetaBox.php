<?php namespace Lib;

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
     */
    public function __construct( $id, $title, $screens, $prefix ) {
        $this->id = $id;
        $this->title = $title;
        $this->screens = $screens;
        $this->prefix = $prefix;
        $this->metaName = $this->prefix . $this->id;

        add_action( 'add_meta_boxes', array( $this, 'addMetaBox' ) );
        add_action( 'save_post', array( $this, 'saveMetaBox' ) );
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
                    $screen
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
                    <th scope="row">
                        <label for="<?php echo $field['name']; ?>"><?php echo $field['label']; ?></label>
                    </th>
                    <td>
                        <?php
                        $meta = get_post_meta( $post->ID, $this->prefix . $this->id, true );
                        $methodName = 'show' . strtoupper( $field['type'] ) . 'Field';
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
     * @return void
     */
    public function saveMetaBox( $postId ) {
        $isAutoSave = wp_is_post_autosave( $postId );
        $isRevision = wp_is_post_revision( $postId );
        $isValidNonce = ( isset( $_POST[$this->metaName . '_nonce'] )
                && wp_verify_nonce( $_POST[$this->metaName . '_nonce'], basename( __FILE__ ) ) ) ? true : false;
        if ( $isAutoSave || $isRevision || $isValidNonce ) {
            return;
        }

        foreach ( $this->fields as $field ) {
            $metaName = $field['name'];
            $single = !isset( $field['multiple'] ) || !$field['multiple'] ? false : true;
            $oldMeta = get_post_meta( $postId, $this->metaName, true );

            $postMeta = isset( $_POST[$this->metaName] ) ? $_POST[$this->metaName] : false;
            if ( !$postMeta ) {
                return;
            }
            $newMetaValue = isset( $postMeta[$metaName] ) ? $postMeta[$metaName] : ( $single ? '' : array() );

            $this->saveField( $postId, $field, $oldMeta, $newMetaValue );
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
     * @return void
     */
    protected function saveField( $postId, $field, $oldMeta, $newMetaValue ) {
        $metaName = $field['name'];
        unset( $oldMeta[$metaName] );
        update_post_meta( $postId, $this->metaName, $oldMeta );

        if ( empty( $newMetaValue ) ) {
            return;
        }
        if ( !is_array( $newMetaValue ) ) {
            $newMetaValue = trim( $newMetaValue );
        }
        $oldMeta[$metaName] = $newMetaValue;
        update_post_meta( $postId, $this->metaName, $oldMeta );
    }

    /**
     * Add a text field.
     *
     * @since 1.0.0
     * @param string $name
     * @param array $attributes
     * @param string $label
     * @return void
     */
    public function addTextField( $name, $attributes = array(), $label = '' ) {
        $this->fields[] = array(
                'type' => 'text',
                'name' => $name,
                'attributes' => $attributes,
                'label' => $label
        );
    }

    /**
     * Add a text area field.
     *
     * @since 1.0.0
     * @param string $name
     * @param array $attributes
     * @param string $label
     * @return void
     */
    public function addTextAreaField( $name, $attributes = array(), $label = '' ) {
        $this->fields[] = array(
                'type' => 'textArea',
                'name' => $name,
                'attributes' => $attributes,
                'label' => $label
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
     * @return void
     */
    public function addSelectField( $name, $options, $multiple, $attributes = array(), $label = '' ) {
        $this->fields[] = array(
                'type' => 'select',
                'name' => $name,
                'options' => $options,
                'multiple' => $multiple,
                'attributes' => $attributes,
                'label' => $label
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
        $value = isset( $meta[$field['name']] ) ? $meta[$field['name']] : '';
        ?>
        <input id="<?php echo $field['name']; ?>"
                type="text" name="<?php echo $this->metaName; ?>[<?php echo $field['name']; ?>]"
                value="<?php echo esc_attr( $value ); ?>"
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
        $value = isset( $meta[$field['name']] ) ? $meta[$field['name']] : '';
        $multiple = isset( $field['multiple'] ) && $field['multiple'] ? true : false;
        $name = $this->metaName . '[' . $field['name'] . ']';
        if ( $multiple ) {
            $name .= '[]';
        }
        ?>
        <select id="<?php echo $field['name']; ?>"
                name="<?php echo $name; ?>"
                value="<?php echo $value; ?>"
                <?php echo $multiple ? 'multiple="multiple"' : ''; ?>
                <?php $this->generateElementAttributes( $field['attributes'] ); ?>>
            <?php foreach ( $field['options'] as $key => $option ) : ?>
                <?php
                if ( !is_array( $value ) ) {
                    $selected = $value === $key ? 'selected' : '';
                } else {
                    $selected = in_array( $key, $value ) ? 'selected' : '';
                }
                ?>
                <option value="<?php echo $key; ?>" <?php echo $selected; ?>>
                    <?php echo $option; ?>
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
        $value = isset( $meta[$field['name']] ) ? $meta[$field['name']] : '';
        ?>
        <textarea id="<?php echo $field['name']; ?>"
                name="<?php echo $this->metaName; ?>[<?php echo $field['name']; ?>]"
                <?php $this->generateElementAttributes( $field['attributes'] ); ?>><?php echo esc_attr( $value ); ?></textarea>
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
            $elementAttributes .= $key . '="' . $value . '"';
        }
        echo $elementAttributes;
    }
}
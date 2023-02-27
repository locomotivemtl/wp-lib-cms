<div <?php echo acf_esc_attrs( $div_attrs ); ?>>
    <?php acf_hidden_input( [
        'name'  => $field['name'],
        'value' => $field['value'],
    ] ); ?>
    <div <?php echo acf_esc_attrs( $wrap_attrs ); ?>>
        <video <?php echo acf_esc_attrs( $video_attrs ); ?>>
            <source <?php echo acf_esc_attrs( $source_attrs ); ?> />
        </video>
        <div class="acf-actions -hover">
            <?php if ( 'basic' !== $uploader ) : ?>
            <a class="acf-icon -pencil dark" data-name="edit" href="#" title="<?php _e( 'Edit', 'acf' ); ?>"></a>
            <?php endif; ?>
            <a class="acf-icon -cancel dark" data-name="remove" href="#" title="<?php _e( 'Remove', 'acf' ); ?>"></a>
        </div>
    </div>
    <div class="hide-if-value">
        <?php if ( 'basic' === $uploader ) : ?>
            <?php if ( $field['value'] && ! is_numeric( $field['value'] ) ) : ?>
                <div class="acf-error-message"><p><?php echo acf_esc_html( $field['value'] ); ?></p></div>
            <?php endif; ?>
            <label class="acf-basic-uploader">
                <?php acf_file_input( [
                    'name' => $field['name'],
                    'id'   => $field['id'],
                ] ); ?>
            </label>
        <?php else : ?>
            <p><?php _e( 'No video selected', 'acf' ); ?> <a data-name="add" class="acf-button button" href="#"><?php _e( 'Add Video', 'acf' ); ?></a></p>
        <?php endif; ?>
    </div>
</div>
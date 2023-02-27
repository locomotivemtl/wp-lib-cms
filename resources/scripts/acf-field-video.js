/**
 * Advanced Custom Fields: Video Field Type
 *
 * This field type is based on the official Image field type.
 */

(function($, acf, undefined) {

    var FIELD_TYPE = 'video';

    /**
     * Video Field
     */
    var Field = acf.models.ImageField.extend({
        type: FIELD_TYPE,

        /**
         * @overrides acf.models.ImageField.$control()
         *
         * @return {Element}
         */
        $control: function () {
            return this.$('.acf-video-uploader');
        },

        /**
         * @overrides acf.models.ImageField.validateAttachment()
         *
         * @param  {object} attachment
         * @return {void}
         */
        validateAttachment: function ( attachment ) {
            // Use WP attachment attributes when available.
            if ( attachment && attachment.attributes ) {
                attachment = attachment.attributes;
            }

            // Apply defaults.
            attachment = acf.parseArgs(attachment, {
                id: 0,
                url: '',
                alt: '',
                title: '',
                caption: '',
                description: '',
            });

            // Return.
            return attachment;
        },

        /**
         * @overrides acf.models.ImageField.render()
         *
         * @param  {object} attachment
         * @return {void}
         */
        render: function ( attachment ) {
            attachment = this.validateAttachment( attachment );

            // Update DOM.
            var $video = this.$('video');

            $video.find('source').first().attr({
                src:  attachment.url,
                type: attachment.mime,
            });

            if ( attachment.id ) {
                this.val( attachment.id );
                this.$control().addClass('has-value');
            } else {
                this.val( '' );
                this.$control().removeClass('has-value');
            }
        },

        /**
         * @overrides acf.models.ImageField.selectAttachment()
         *
         * @return {void}
         */
        selectAttachment: function () {
            // vars
            var parent   = this.parent();
            var multiple = (parent && parent.get('type') === 'repeater');

            // new frame
            var frame = acf.newMediaPopup({
                mode:           'select',
                type:           'video',
                title:          acf.__('Select Video'),
                field:          this.get('key'),
                multiple:       multiple,
                library:        this.get('library'),
                allowedTypes:   this.get('mime_types'),
                select:         $.proxy(function( attachment, i ) {
                    if ( i > 0 ) {
                        this.append( attachment, parent );
                    } else {
                        this.render( attachment );
                    }
                }, this)
            });
        },

        /**
         * @overrides acf.models.ImageField.editAttachment()
         *
         * @return {void}
         */
        editAttachment: function () {
            // vars
            var val = this.val();

            // bail early if no val
            if ( ! val ) {
                return;
            }

            // popup
            var frame = acf.newMediaPopup({
                mode:       'edit',
                title:      acf.__('Edit Video'),
                button:     acf.__('Update Video'),
                attachment: val,
                field:      this.get('key'),
                select:     $.proxy(function ( attachment, i ) {
                    this.render( attachment );
                }, this)
            });
        }
    });

    acf.registerFieldType(Field);

    acf.registerConditionForFieldType('hasValue', FIELD_TYPE);
    acf.registerConditionForFieldType('hasNoValue', FIELD_TYPE);

})(jQuery, acf);

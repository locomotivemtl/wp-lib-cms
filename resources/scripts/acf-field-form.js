/**
 * Advanced Custom Fields: Form Field Type
 */

(function($, acf, undefined) {

    var FIELD_TYPE = 'form';

    /**
     * Form Field
     */
    var Field = acf.models.SelectField.extend({
        type: FIELD_TYPE,
    });

    acf.registerFieldType(Field);

    acf.registerConditionForFieldType('hasValue', FIELD_TYPE);
    acf.registerConditionForFieldType('hasNoValue', FIELD_TYPE);

})(jQuery, acf);

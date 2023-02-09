define([
        'Magento_Ui/js/grid/columns/column'
    ],
    function (Column) {
        'use strict';

        return Column.extend({
            defaults: {
                bodyTmpl: 'nofraud_checkout/grid/cells/link',

            },

            getFieldHandler: function (record) {
                return false;
            }
        });
    }
);

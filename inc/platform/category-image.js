(function ($) {
    'use strict';
    $(document).on('click', '.zk-category-image-select', function () {
        var control = $(this).closest('.zk-category-image-control');
        var frame = wp.media({ title: 'Category featured image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var preview = attachment.sizes && (attachment.sizes.medium || attachment.sizes.thumbnail);
            control.find('[name="zk_category_featured_image"]').val(attachment.id);
            control.find('[name="zk_category_image_removed"]').val('0');
            control.find('.zk-category-image-preview').empty().append($('<img>', { src: preview ? preview.url : attachment.url, alt: attachment.alt || '', css: { maxWidth: '100%', height: 'auto' } }));
            control.find('.zk-category-image-remove').show();
        });
        frame.open();
    }).on('click', '.zk-category-image-remove', function () {
        var control = $(this).closest('.zk-category-image-control');
        control.find('[name="zk_category_featured_image"]').val('0');
        control.find('[name="zk_category_image_removed"]').val('1');
        control.find('.zk-category-image-preview').empty();
        $(this).hide();
    });
    $(document).ajaxSuccess(function (event, xhr, settings) {
        if (settings.data && /(?:^|&)action=add-tag(?:&|$)/.test(settings.data) && /taxonomy=category(?:&|$)/.test(settings.data) && xhr.responseXML && !xhr.responseXML.querySelector('wp_error')) {
            $('#addtag .zk-category-image-remove').trigger('click');
        }
    });
})(jQuery);

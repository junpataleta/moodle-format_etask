// Popover.
require(['jquery', 'theme_boost/tether'], function($, Tether) {
    window.jQuery = $;
    window.Tether = Tether;
    require(['theme_boost/popover'], function() {
        $('[data-toggle="popover"]').popover({
            html: true,
            container: 'body',
            placement: 'bottom',
            trigger: 'hover',
            sanitize: false
        });
    });
});

// Dialog grade settings.
require(['jquery', 'core/modal_factory'], function($, ModalFactory) {
    var elements = $('.grade-item-dialog');
    $.each(elements, function(index, element) {
        var trigger = $('#' + element.id);
        var gradeSettings = $('#grade-settings-' + element.id);
        var title = $(gradeSettings).find('.title');
        var body = $(gradeSettings).find('.grade-settings-form');

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: title.text(),
            body: body.html()
        }, trigger).done(function(modal) {
            $('.grade-item-dialog').css('opacity', '1');
            var select = $(modal.body).find('select');
            var saveButton = $(modal.footer).find('.btn-primary');
            $(saveButton).click(function() {
                var formId = $(element).attr('id').match(/\d+/);
                $('select[name=gradePass' + formId + ']').val($(select).val());
                $('#grade-pass-form' + formId).submit();
            });
        });
    });
});

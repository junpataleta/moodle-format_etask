// Popover.
require(['jquery', 'jqueryui'], function($, jqui) {
    window.jQuery = $;
    require([], function() {
        $('[data-toggle="popover"]').popover({
            html: true,
            container: 'body',
            placement: 'bottom',
            trigger: 'hover'
        });
    });
});

// Dialog grade settings.
require(['jquery', 'jqueryui'], function($, jqui) {
    window.jQuery = $;
    var elements = $('.grade-item-dialog');
    $.each(elements, function(index, element) {
        var trigger = $('#' + element.id);
        var gradeSettings = $('#grade-settings-' + element.id);

        require([], function() {
            $(trigger).click(function(){
                $(gradeSettings).modal('show');
            });

            $('.grade-item-dialog').css('opacity', '1');
            var select = $(gradeSettings).find('.modal-body').find('select');
            var saveButton = $(gradeSettings).find('.modal-footer').find('.btn-primary');
            $(saveButton).click(function() {
                var formId = $(element).attr('id').match(/\d+/);
                $('select[name=gradePass' + formId + ']').val($(select).val());
                $('#grade-pass-form' + formId).submit();
            });
        });
    });
});

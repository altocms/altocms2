;var alto = alto || {};

alto.userfield = ( function ($) {
    "use strict";
    var $that = this;

    this.init = function() {
        this.modalWindow = $('#modal-userfield');
        this.fieldIdPrefix = '#userfield_';
        this.iCountMax = 2;
    };

    this.addUserfieldDialog = function () {
        this.modalWindow.find('.modal-title').text(alto.lang.get('action.admin.user_field_admin_title_add'));
        this.modalWindow.find('.btn-primary').text(alto.lang.get('action.admin.user_field_add'));
        $('#user_fields_form_name').val('');
        $('#user_fields_form_title').val('');
        $('#user_fields_form_id').val('');
        $('#user_fields_form_pattern').val('');
        $('#user_fields_form_type').val('');
        $('#user_fields_form_action').val('add');
        this.modalWindow.modal('show');
        return false;
    };

    this.updateUserfieldDialog = function (id) {
        this.modalWindow.find('.modal-title').text(alto.lang.get('action.admin.user_field_admin_title_edit'));
        this.modalWindow.find('.btn-primary').text(alto.lang.get('action.admin.user_field_update'));
        $('#user_fields_form_action').val('update');
        var field = $(this.fieldIdPrefix + id);

        $('#user_fields_form_name').val(field.find('.userfield_admin_name').text());
        $('#user_fields_form_title').val(field.find('.userfield_admin_title').text());
        $('#user_fields_form_pattern').val(field.find('.userfield_admin_pattern').text());
        $('#user_fields_form_type').find('[value="' + field.find('.userfield_admin_type').text() + '"]').prop('selected', true).trigger('refresh');
        $('#user_fields_form_id').val(id);
        this.modalWindow.modal('show');
        return false;
    };

    this.applyForm = function () {
        this.modalWindow.modal('hide');
        if ($('#user_fields_form_action').val() == 'add') {
            this.addUserfield();
        } else if ($('#user_fields_form_action').val() == 'update') {
            this.updateUserfield();
        }
    };

    this.addUserfield = function () {
        var name = $('#user_fields_form_name').val(),
            title = $('#user_fields_form_title').val(),
            pattern = $('#user_fields_form_pattern').val(),
            type = $('#user_fields_form_type').val(),
            url = alto.routerUrl('admin') + 'settings-userfields',
            params = {'action': 'add', 'name': name, 'title': title, 'pattern': pattern, 'type': type};

        alto.progressStart();
        alto.ajax(url, params, function (response) {
            alto.progressDone();
            if (!response) {
                alto.msg.error(null, 'System error #1001');
            } else if (!response.bStateError) {
                var html = $($that.fieldIdPrefix + 'ID').outerHTML();
                html = html.replace(/ID/g, response.id);
                var field = $(html).show();
                field.find('.userfield_admin_name').text(name);
                field.find('.userfield_admin_title').text(title);
                field.find('.userfield_admin_pattern').text(pattern);
                field.find('.userfield_admin_type').text(type);
                $('#user_field_list').append(field);
                alto.msg.notice(response.sMsgTitle, response.sMsg);
            } else {
                alto.msg.error(response.sMsgTitle, response.sMsg);
            }
        });
    };

    this.updateUserfield = function () {
        var id = $('#user_fields_form_id').val(),
            name = $('#user_fields_form_name').val(),
            title = $('#user_fields_form_title').val(),
            pattern = $('#user_fields_form_pattern').val(),
            type = $('#user_fields_form_type').val(),
            url = alto.routerUrl('admin') + 'settings-userfields',
            params = {'action': 'update', 'id': id, 'name': name, 'title': title, 'pattern': pattern, 'type': type};

        alto.progressStart();
        alto.ajax(url, params, function (response) {
            alto.progressDone();
            if (!response) {
                alto.msg.error(null, 'System error #1001');
            } else if (!response.bStateError) {
                var field = $($that.fieldIdPrefix + id);
                field.find('.userfield_admin_name').text(name);
                field.find('.userfield_admin_title').text(title);
                field.find('.userfield_admin_pattern').text(pattern);
                field.find('.userfield_admin_type').text(type);
                // *depricated* //ls.hook.run('ls_userfield_update_userfield_after', [params, response]);
                alto.msg.notice(response.sMsgTitle, response.sMsg);
            } else {
                alto.msg.error(response.sMsgTitle, response.sMsg);
            }
        });
    };

    this.deleteUserfield = function (id) {
        var title = alto.lang.get('action.admin.user_field_delete_confirm_title'),
            text = alto.lang.get('action.admin.user_field_delete_confirm_text', {field: $(this.fieldIdPrefix + id).find('.userfield_admin_name').text()});

        alto.modal.confirm({title: title, text: text, onConfirm: function () {
            var url = alto.routerUrl('admin') + 'settings-userfields',
                params = {'action': 'delete', 'id': id};

            alto.progressStart();
            alto.ajax(url, params, function (response) {
                alto.progressDone();
                if (!response) {
                    alto.msg.error(null, 'System error #1001');
                } else if (!response.bStateError) {
                    $($that.fieldIdPrefix + id).remove();
                    alto.msg.notice(response.sMsgTitle, response.sMsg);
                    // *depricated* //ls.hook.run('ls_userfield_update_userfield_after', [params, response]);
                } else {
                    alto.msg.error(response.sMsgTitle, response.sMsg);
                }
            });
        }});
        return false;
    };

    $(function() {
        alto.userfield.init();
    });

    return this;
}).call(alto.userfield || {}, jQuery);